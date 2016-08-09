<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    App\Model\Opts,
    Exception,
    Nette\Utils\DateTime as DateTime;

/**
 * ItemStat global class
 */
class HostStat extends Monda {

    static function hostsToIds() {
        if (Opts::getOpt("hostgroups")) {
            $hq = Array(
                "selectHosts" => "refer",
                "output" => "extend",
                "filter" => Array(
                    "name" => Opts::getOpt("hostgroups")
                )
            );
            $hg = Monda::apiCmd("hostGroupGet", $hq);
            foreach ($hg as $hostgroup) {
                foreach ($hostgroup->hosts as $host) {
                    $hostids[] = $host->hostid;
                }
            }
            if (count($hostids)==0) {
                CliDebug::err("Hostgroup(s) ".join(",".Opts::getOpt("hostgroups"))." has no hosts!\n");
            }
        }
        if (Opts::getOpt("hosts")) {
            $hostids = Array();
            $iq = Array(
                "monitored" => true
            );
            $iq["filter"]["host"] = Opts::getOpt("hosts");
            $h = Monda::apiCmd("hostGet", $iq);
            foreach ($h as $host) {
                $hostids[] = $host->hostid;
            }
            if (count($hostids)!=count(Opts::getOpt("hosts"))) {
                CliDebug::err("Host(s) ".join(",".Opts::getOpt("hosts"))." do not exists!\n");
            }
        }
        if (count($hostids)==0) {
            CliDebug::err("You have to specify hostgroups, hosts or hostids to process!\n");
        }
        Opts::setOpt("hostids", $hostids);
        return;
    }

    static function hosts2itemids($hostids) {
        $itemids = Array();
        $c = 1;
        $hostcount = count($hostids);
        foreach ($hostids as $hostid) {
            $iq = Array(
                "monitored" => true,
                "hostids" => Array($hostid)
            );
            CliDebug::dbg("Querying items ($c of $hostcount hosts)\n");
            $i = Monda::apiCmd("itemGet", $iq);
            if (count($i) > 0) {
                foreach ($i as $item) {
                    $itemids[] = $item->itemid;
                }
            }
            $c++;
        }
        return($itemids);
    }

    static function host2id($host) {
        $iq = Array(
            "monitored" => true,
            "filter" => Array(
                "name" => Array($host)
            )
        );
        $h = Monda::apiCmd("hostGet", $iq);
        return($h->hostid);
    }

    static function hsSearch() {
        $wids = Tw::twToIds();
        if (count($wids) == 0) {
            throw New Exception("No windows to process.");
        }
        $ids = self::mquery("
            SELECT
              hoststat.hostid AS hostid,
              hoststat.windowid AS windowid,
              hoststat.cnt AS cnt,
              hoststat.items AS items,
              hoststat.loi AS loi
            FROM hoststat
            WHERE
             hoststat.windowid IN (?)
             AND hoststat.hostid IN (?)
             AND hoststat.loi>?
             ORDER BY hoststat.loi DESC
             LIMIT ?
            ", $wids, Opts::getOpt("hostids"), Opts::getOpt("hs_minloi"), Opts::getOpt("max_rows"));
        return($ids);
    }

    static function hsStats() {
        Opts::setOpt("max_rows",Monda::_MAX_ROWS);
        $wids = Tw::twToIds();
        if (count($wids) == 0) {
            throw New Exception("No windows to process.");
        }
        if (is_array(Opts::getOpt("hostids"))) {
            $hostidsql="AND hoststat.hostid IN (".join(",",Opts::getOpt("hostids")).") ";
        } else {
            $hostidsql="";
        }
        $ids = self::mquery("
            SELECT
              hoststat.hostid AS hostid,
              AVG(hoststat.cnt) AS cnt,
              AVG(hoststat.items) AS items,
              AVG(hoststat.loi) AS loi
            FROM hoststat
            WHERE
             hoststat.windowid IN (?)
             $hostidsql
             AND hoststat.loi>?
             GROUP BY hoststat.hostid
             ORDER BY AVG(hoststat.loi) DESC
             LIMIT ?
            ", $wids, Opts::getOpt("hs_minloi"), Opts::getOpt("max_rows"));
        return($ids);
    }

    static function hsToIds($pkey = false) {
        $ids = self::hsSearch();
        if (!$ids) {
            throw new Exception("No hosts to process.");
        }
        $rows = $ids->fetchAll();
        $hostids = Array();
        foreach ($rows as $row) {
            if ($pkey) {
                $hostids[] = Array(
                    "hostid" => $row->hostid,
                    "windowid" => $row->windowid
                );
            } else {
                $hostids[] = $row->hostid;
            }
        }
        return($hostids);
    }

    static function hsUpdate() {
        Opts::setOpt("max_rows",Monda::_MAX_ROWS);
        $hostids = Opts::getOpt("hostids");
        $itemids = self::hosts2itemids($hostids);
        $wids = Tw::twToIds();
        CliDebug::warn(sprintf("Need to update HostStat for %d windows, %d hosts and %d items...", count($wids), count($hostids), count($itemids)));
        if (count($wids) == 0 || count($hostids) < 1 || count($itemids) < 1) {
            return(false);
        }
        if (Opts::getOpt("hs_update_unknown")) {
            $ius = self::mquery("UPDATE itemstat
                SET hostid=NULL
                WHERE itemid IN (?) AND windowid IN (?) AND hostid=-1", $itemids, $wids);
        }
        $ius = self::mquery("SELECT COUNT(*) AS cnt FROM itemstat WHERE itemid IN (?) AND windowid IN (?) AND (hostid IS NULL OR hostid=-1)", $itemids, $wids)->fetch();
        if ($ius->cnt > 0) {
            foreach ($hostids as $hostid) {
                $hitemids = self::hosts2itemids(array($hostid));
                CliDebug::info(sprintf("Host %d of %d (%d items),", $hostid, count($hostids), count($hitemids)));
                if (count($hitemids) < 1)
                    continue;
                $ius = self::mquery("
                UPDATE itemstat
                SET hostid=?
                WHERE itemid IN (?) AND windowid IN (?) AND hostid IS NULL", $hostid, $hitemids, $wids);
            }
            $ius = self::mquery("
            UPDATE itemstat
            SET hostid=?
            WHERE itemid IN (?) AND windowid IN (?) AND hostid IS NULL", -1, $itemids, $wids);
        }
        
        CliDebug::warn("\n");
    }

    static function hsDelete() {
        Opts::setDOpt("max_rows",Monda::_MAX_ROWS);
        $tws = Tw::twToIds();
        $dq = self::mquery("DELETE FROM hoststat WHERE windowid IN (?) AND hostid IN (?)", $tws,Opts::getOpt("hostids"));
    }

    static function hsMultiCompute() {
        Opts::setDOpt("max_rows",Monda::_MAX_ROWS);
        $wids = Tw::twToIds();
        CliDebug::warn(sprintf("Need to compute HostStat for %d windows...", count($wids)));
        if (count($wids) == 0) {
            throw New Exception("No windows to process.");
        }
        if (is_array(Opts::getOpt("hostids"))) {
            $hostidssql="AND itemstat.hostid IN (".join(",",Opts::getOpt("hostids")).") ";
        } else {
            $hostidssql="";
        }
        $stat = self::mquery("
            SELECT itemstat.hostid AS hostid,
                itemstat.windowid AS windowid,
                AVG(cv) AS cv,
                SUM(itemstat.loi) AS loi,
                COUNT(DISTINCT itemid) AS items,
                SUM(itemstat.cnt) AS cnt
            FROM itemstat
            WHERE itemstat.windowid IN (?)
              $hostidssql
              AND itemstat.cnt>0
            GROUP BY itemstat.hostid,itemstat.windowid
            ", $wids);
        $rows = $stat->fetchAll();
        $i = 0;
        foreach ($rows as $row) {
            CliDebug::info(".");
            self::mbegin();
            $i++;
            CliDebug::info(sprintf("Computing HostStat for host %s and window %s (%d of %d)\n", $row->hostid, $row->windowid, $i, count($rows)));
            $sd = self::mquery("DELETE FROM hoststat WHERE windowid=? AND hostid=?", $row->windowid, $row->hostid
            );
            $su = self::mquery("
                INSERT INTO hoststat", Array(
                        "hostid" => $row->hostid,
                        "windowid" => $row->windowid,
                        "cnt" => $row->cnt,
                        "items" => $row->items,
                        "loi" => 0,
                        "updated" => New DateTime()
                            )
            );
            self::mcommit();
        }
        CliDebug::warn("\n");
    }

    static function hsLoi() {
        $wids = Tw::twToIds();
        CliDebug::warn(sprintf("Need to compute HostStat Loi on %d windows...", count($wids)));
        if (count($wids) == 0) {
            throw New Exception("No hosts to process.");
        }
        self::mbegin();
        $stats = self::mquery("SELECT
                  windowid,
                  MAX(cnt) AS maxcnt,
                  MIN(cnt) AS mincnt,
                  MAX(items) AS maxitems,
                  MIN(items) AS minitems
                FROM hoststat
                WHERE windowid IN (?)
                GROUP BY windowid", Tw::twToIds())->fetchAll();
        foreach ($stats as $s) {
            foreach (Opts::getOpt("hostids") as $hostid) {
                $lq = self::mquery("UPDATE hoststat set loi=50*(cnt/?)+50*(items/?) WHERE windowid=? AND hostid=?", $s->maxcnt, $s->maxitems, $s->windowid, $hostid);
            }
        }
        self::mcommit();
        CliDebug::warn("Done\n");
    }

}

?>
