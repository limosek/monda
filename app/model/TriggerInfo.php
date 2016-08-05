<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Tracy\Debugger,
    Exception,
    Nette\Utils\DateTime as DateTime,
    Nette\Database\Context;

/**
 * ItemStat global class
 */
class TriggerInfo extends Monda {

    static function Info($triggerid) {
        $tq = Array(
            "triggerids" => $triggerid,
            "output" => "extend",
            "selectFunctions" => "extend"
        );
        $triggers = Monda::apiCmd("triggerGet", $tq);
        return($triggers[0]);
    }

    static function expandTriggerParams($trigger) {
        return($trigger);
    }

    static function expandTrigger($triggerid, $withhost = false) {
        $ii = self::Info($triggerid);
        $ii = self::expandTriggerParams($ii);
        if (count($ii) > 0) {
            $itxt = $ii->description;
            if (Opts::getOpt("anonymize_items")) {
                $itxt = Util::encrypt($itxt, Opts::getOpt("anonymize_key"));
            } else {
                if (Opts::getOpt("item_restricted_chars")) {
                    $itxt = strtr($itxt, Opts::getOpt("item_restricted_chars"), "_____________");
                }
            }
            if ($withhost) {
                return(HsPresenter::expandHost($ii[0]->hostid) . ":" . $itxt);
            } else {
                return($itxt);
            }
        } else {
            return("unknown");
        }
    }

    static public function ExpandHistory($clocks, $tdata) {
        $ret = Util::interpolate($tdata, $clocks, true);
        foreach ($ret as $clock => $value) {

            if ($value == 0) {
                $ret[$clock] = "OK";
            } else {
                $ret[$clock] = "PROBLEM";
            }
        }
        return($ret);
    }
    
    static public function Triggers2Items($triggerids) {
        $tq=Array(
            "triggerids" => $triggerids,
            "output" => "extend",
            "selectFunctions" => "extend"
        );
        $tr=Monda::apiCmd("triggerGet",$tq);
        $itemids=Array();
        foreach ($tr as $trigger) {
            if ($trigger->functions) {
                foreach ($trigger->functions as $function) {
                    $itemids[$function->itemid]=$function->itemid;
                }
            }
        }
        return($itemids);
    }
    
    static public function Triggers2Events($start, $end, $triggerids) {
        $eq = Array(
            "time_from" => $start,
            "time_till" => $end,
            "output" => "extend",
            "objectids" => $triggerids,
            "selectHosts" => "refer",
            "selectRelatedObject" => "refer",
            "select_alerts" => "refer",
            "select_acknowledges" => "refer",
            "sortfield" => "clock"
        );
        $events = Monda::apiCmd("eventGet", $eq);
        CliDebug::warn(sprintf("Found %d events for triggerids (<%d,%d>)%s.\n", count($events), $start, $end, join(",", $triggerids)));
        return($events);
    }

    static public function History($triggerids, $clocks) {
        if (!$triggerids) {
            return(false);
        } else {
            $events=self::Triggers2Events(min($clocks) - Opts::getOpt("events_prefetch"),  max($clocks), Opts::getOpt("triggerids"));
            $rows = Array();
            $tdata = Array();
            $tinfo = Array();
            foreach ($events as $e) {
                $tid = $e->relatedObject->triggerid;
                $clock = round($e->clock / Opts::getOpt("history_granularity"))*Opts::getOpt("history_granularity"); 
                if (!$tinfo[$tid]["minclock"]) {
                    $tinfo[$tid]["minclock"] = $clock;
                    $tinfo[$tid]["maxclock"] = $clock;
                    $tinfo[$tid]["count"] = 1;
                    $tinfo[$tid]["description"] = self::expandTrigger($tid);
                }
                $tinfo[$tid]["minclock"] = min($clock, $tinfo[$tid]["minclock"]);
                $tinfo[$tid]["maxclock"] = max($clock, $tinfo[$tid]["maxclock"]);
                $tinfo[$tid]["count"] ++;
                if (array_key_exists($clock,$tdata[$tid]))  {
                    contiune;
                } else {
                    $tdata[$tid][$clock] = $e->value;
                }
                CliDebug::info(sprintf("Event %d at %d seconds from start(%d) (%d).\n", $e->eventid, $clock-min($clocks),$e->clock, $e->value));
            }
            foreach ($tinfo as $ti) {
                if ($ti["minclock"] > min($clocks)) {
                    CliDebug::err(sprintf("Increase prefetch time for triggers (--events_prefetch)! Event started at %d.\n", $ti["minclock"]));
                    return(false);
                }
            }
            foreach ($triggerids as $tid) {
                CliDebug::info(sprintf("Expanding %d trigger values for %s from %d values.\n", count($clocks), $tid, count($tdata[$tid])));
                $it[$tid] = self::ExpandHistory($clocks, $tdata[$tid]);
            }
            foreach ($clocks as $c) {
                foreach ($it as $tid => $values) {
                    $rows[$c]["clock"] = $c;
                    $rows[$c][$tid] = $values[$c];
                }
            }
            return(Array($tinfo, $rows));
        }
    }

}

?>
