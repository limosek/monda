<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class EventCorr extends Monda {

    function ecSearch($opts,$start) {
        if ($start+Monda::_1DAY>$opts->end) {
            $end=$opts->end;
        } else {
            $end=$start+Monda::_1DAY;
        }
        CliDebug::warn(sprintf("Searching for events between <%d,%d> ", $start,$end));
        $eq=Array(
            #"hostids" => $opts->hostids,
            "time_from" => $start,
            "time_till" => $end,
            "output" => "extend",
            "selectItems" => "refer",
            "selectHosts" => "refer",
            "select_alerts" => "refer",
            "select_acknowledges" => "refer"
        );
        $events=self::apiCmd("eventGet",$eq);
        CliDebug::warn(sprintf("Found %d events, updating LOI.\n",count($events)));
        return($events);
    }
    
    function ecLoi($opts) {
        $start = $opts->start;
        self::mbegin();
        while ($start < $opts->end) {
            $events = self::ecSearch($opts, $start);
            $start += Monda::_1DAY;
            if (count($events) == 0) {
                return;
            }
            foreach ($events as $e) {
                $w = Tw::twSearchClock($e->clock)->fetchAll();
                $wids = self::extractIds($w, array("id"));
                self::mquery("UPDATE timewindow SET loi=loi+? WHERE id IN (?)", $opts->inc_loi_event_window, $wids["id"]);
                if (isset($e->hosts)) {
                    foreach ($e->hosts as $h) {
                        self::mquery("UPDATE hoststat SET loi=loi+? WHERE hostid=? AND windowid IN (?)", $opts->inc_loi_event_host, $h->hostid, $wids["id"]);
                    }
                }
                if (isset($e->items)) {
                    foreach ($e->items as $i) {
                        self::mquery("UPDATE itemstat SET loi=loi+? WHERE hostid=? AND windowid IN (?)", $opts->inc_loi_event_item, $i->itemid, $wids["id"]);
                    }
                }
            }
        }
        self::mcommit();
    }

}

?>
