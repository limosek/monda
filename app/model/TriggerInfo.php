<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Tracy\Debugger,
    App\Model\Hoststat,
    App\Presenters\HsPresenter,
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

    static function expandTriggerParams($trigger,$host) {
        $ii = self::Info($triggerid);
        $itxt = HsPresenter::expandHost($host->hostid);
        $trigger->description=str_replace("{HOST.NAME}",$itxt,$trigger->description);
        $trigger->description=str_replace("{HOSTNAME}",$itxt,$trigger->description);
        return($trigger);
    }

    static function expandTrigger($triggerid, $hosts, $withhost = false) {
        $ii = self::Info($triggerid);
        if ($hosts) {
            $ii = self::expandTriggerParams($ii,$hosts[0]);
            if (count($ii) > 0) {
                $itxt = $ii->description;
                if ($withhost) {
                    $itxt = HsPresenter::expandHost($hosts[0]->hostid) . ":" . $itxt;
                }
            } else {
                $itxt = "unknown";
            }
        } else {
            $itxt = "unknown";
        }
        if (Opts::getOpt("anonymize_items")) {
            $itxt = Util::encrypt($itxt, Opts::getOpt("anonymize_key"));
        } elseif (Opts::getOpt("item_restricted_chars")) {
            $itxt = strtr($itxt, Opts::getOpt("item_restricted_chars"), "_______________");
        }
        return($itxt);
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
    
    static public function Hosts2Triggers($hostids) {
        $tq = Array(
            "hostids" => Opts::getOpt("hostids")
        );
        $tr = Monda::apiCmd("triggerGet", $tq);
        $tids = Array();
        foreach ($tr as $trigger) {
            $tids[$trigger->triggerid] = $trigger->triggerid;
        }
        return($tids);
    }

    static public function Triggers2Items($triggerids) {
        $tq = Array(
            "triggerids" => $triggerids,
            "output" => "extend",
            "selectFunctions" => "extend"
        );
        $tr = Monda::apiCmd("triggerGet", $tq);
        $itemids = Array();
        foreach ($tr as $trigger) {
            if ($trigger->functions) {
                foreach ($trigger->functions as $function) {
                    $itemids[$function->itemid] = $function->itemid;
                }
            }
        }
        return($itemids);
    }

    static public function Triggers2Events($start, $end, $triggerids) {
        $eq = Array(
            "output" => "extend",
            "objectids" => $triggerids,
            "selectHosts" => "refer",
            "selectRelatedObject" => "refer",
            "select_alerts" => "refer",
            "select_acknowledges" => "refer",
            "sortfield" => "clock"
        );
        if ($start) $eq["time_from"]=$start;
        if ($end) $eq["time_till"]=$end;
        $events = Monda::apiCmd("eventGet", $eq);
        CliDebug::warn(sprintf("Found %d events for triggerids (<%d,%d>)%s.\n", count($events), $start, $end, join(",", $triggerids)));
        return($events);
    }
    
    static public function eventValueForInterval($events, $fstamp, $tstamp, $tid=false) {
        $lastclock = $fstamp;
        $lastvalue = false;
        $value=false;
        foreach ($events as $event) {
            if ($tid && $tid != $event->relatedObject->triggerid) continue;
            if ($event->clock >= $fstamp) {
                if ($event->clock < $tstamp) {
                    //CliDebug::dbg(sprintf("clock inside: %d\n",$event->clock-$fstamp));
                    if ($lastvalue === false) {
                        CliDebug::err(sprintf("Increase prefetch time for triggers (--events_prefetch)!)\n"));
                        return(false);
                    } else {
                        $value += ($event->clock - max($lastclock, $fstamp)) * $lastvalue;
                        $lastclock = $event->clock;
                        $lastvalue = (int) $event->value;
                    }
                } else {
                    $value+=($tstamp-$lastclock)*$lastvalue;
                }
            } else {
                $lastvalue = (int) $event->value;
            }
        }
        if ($value===false) {
            $value=($tstamp-$fstamp)*$lastvalue;
        }
        return(Array(
            $event,
            $value/($tstamp-$fstamp))
              );
    }

    static public function History($triggerids, $clocks, $shift=0) {
        if (!$triggerids) {
            return(false);
        } else {
            $events = self::Triggers2Events(min($clocks) - Opts::getOpt("events_prefetch"), max($clocks), Opts::getOpt("triggerids"));
            $rows = Array();
            foreach ($clocks as $clock) {
                foreach ($triggerids as $tid) {
                    List($event, $value) = TriggerInfo::eventValueForInterval($events, $clock + $shift, $clock + $shift + Opts::getOpt("history_granularity"), $tid);
                    if ($value > Opts::GetOpt("wevent_problem_treshold")) {
                        $value = "PROBLEM";
                    } else {
                        $value = "OK";
                    }
                    $rows[$clock][$tid] = $value;
                }
            }
            return($rows);
        }
    }

}

?>
