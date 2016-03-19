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
        $hostids = Opts::getOpt("hostids");
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
              hoststat.loi AS loi
            FROM hoststat
            WHERE
             hoststat.windowid IN (?)
             AND hoststat.hostid IN (?)
             AND hoststat.loi IS NOT NULL
             ORDER BY hoststat.loi DESC
             LIMIT ?
            ", $wids, Opts::getOpt("hostids"), Opts::getOpt("max_rows"));
        return($ids);
    }

    static function hsStats() {
        $wids = Tw::twToIds();
        if (count($wids) == 0) {
            throw New Exception("No windows to process.");
        }
        $ids = self::mquery("
            SELECT
              hoststat.hostid AS hostid,
              AVG(hoststat.cnt) AS cnt,
              AVG(hoststat.loi) AS loi
            FROM hoststat
            WHERE
             hoststat.windowid IN (?)
             AND hoststat.hostid IN (?)
             AND hoststat.loi IS NOT NULL
             GROUP BY hoststat.hostid
             ORDER BY AVG(hoststat.loi) DESC
             LIMIT ?
            ", $wids, Opts::getOpt("hostids"), Opts::getOpt("max_rows"));
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
        $hostids = Opts::getOpt("hostids");
        $itemids = self::hosts2itemids($hostids);
        $wids = Tw::twToIds();
        CliDebug::warn(sprintf("Need to update HostStat for %d windows, %d hosts and %d items...", count($wids), count($hostids), count($itemids)));
        if (count($wids) == 0 || count($hostids) < 1 || count($itemids) < 1) {
            return(false);
        }
        self::mbegin();
        foreach ($hostids as $hostid) {
            $hitemids = self::hosts2itemids(array($hostid));
            if (count($hitemids) < 1)
                continue;
            $ius = self::mquery("
                UPDATE itemstat
                SET hostid=?
                WHERE itemid IN (?) AND windowid IN (?) AND hostid IS NULL", $hostid, $hitemids, $wids);
        }
        self::mcommit();
        CliDebug::warn("\n");
    }

    static function hsDelete() {
        $ids = self::hsToIds(true);
        self::mbegin();
        foreach ($ids as $id) {
            $dq = self::mquery("DELETE FROM hoststat WHERE ", $id);
        }
        self::mcommit();
    }

    static function hsMultiCompute() {
        $wids = Tw::twToIds();
        CliDebug::warn(sprintf("Need to compute HostStat for %d windows...", count($wids)));
        if (count($wids) == 0 || count(Opts::getOpt("hostids")) == 0) {
            throw New Exception("No hosts to process.");
        }
        $stat = self::mquery("
            SELECT itemstat.hostid AS hostid,
                itemstat.windowid AS windowid,
                AVG(cv) AS cv,
                SUM(itemstat.loi) AS loi,
                COUNT(itemid) AS itemid,
                COUNT(itemstat.cnt) AS cnt
            FROM itemstat
            LEFT JOIN hoststat ON (hoststat.hostid=itemstat.hostid)
            WHERE itemstat.windowid IN (?) AND itemstat.hostid IN (?)
              AND itemstat.cnt>0
            GROUP BY itemstat.hostid,itemstat.windowid
            ", $wids, Opts::getOpt("hostids"));
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
                  MIN(cnt) AS mincnt
                FROM hoststat
                WHERE windowid IN (?)
                GROUP BY windowid", Tw::twToIds())->fetchAll();
        foreach ($stats as $s) {
            foreach (Opts::getOpt("hostids") as $hostid) {
                $lq = self::mquery("UPDATE hoststat set loi=100*cnt/? WHERE windowid=? AND hostid=?", $s->maxcnt, $s->windowid, $hostid);
            }
        }
        self::mcommit();
        CliDebug::warn("Done\n");
    }

}

?>
