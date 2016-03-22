<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \Exception,
    \ZabbixApi;

/**
 * TimeWindow global class
 */
class Tw extends Monda {

    static function twCreate($zid, $start, $length, $description) {

        $id = Monda::mquery("SELECT id FROM timewindow WHERE tfrom=? AND seconds IN (?) AND serverid=?", New DateTime("@$start"), $length, $zid
                )->fetch();
        if ($id == false) {
            CliDebug::dbg("Creating window $start,$length,$description\n");
            return(
                    Monda::mquery("INSERT INTO timewindow", Array(
                        "description" => $description,
                        "tfrom" => New DateTime("@$start"),
                        "seconds" => $length,
                        "created" => New DateTime(),
                        "serverid" => $zid,
                        "parentid" => null,
                        "loi" => 0
            )));
        }
    }

    static function twMultiCreate() {
        
        if (!is_array(Opts::getOpt("window_length"))) {
            throw new Exception("Specify window lengths!");
        }
        $fdate=date("Y-m-d",Opts::getOpt("start"));
        $tdate=date("Y-m-d",Opts::getOpt("end"));
        $lengths=join(",",Opts::getOpt("window_length"));
        CliDebug::warn("Creating windows from $fdate to $tdate and lengths $lengths...");
        Monda::mbegin();
        $start=round(Opts::getOpt("start")/3600,0)*3600;
        $end=(round(Opts::getOpt("end")/3600,0)+1)*3600;
        for ($i = $start; $i < $end; $i = $i + Monda::_1HOUR) {
            foreach (Opts::getOpt("window_length") as $length) {
                if ($length==Monda::_1YEAR && date("m:d:H",$i)=="01:01:00") {
                    Tw::twCreate(Opts::getOpt("zabbix_id"), $i, $length, "Year_" . date("Y", $i));
                }
                if ($length>=Monda::_1MONTH28 && $length<=Monda::_1MONTH31 && date("d:H",$i)=="01:00") {
                    $monthseconds = date("t", $i) * 3600 * 24;
                    Tw::twCreate(Opts::getOpt("zabbix_id"), $i, $monthseconds, "Month_" . date("Y-m", $i));
                }
                if ($length==Monda::_1WEEK && date("N-H",$i)=="1-00") {
                    Tw::twCreate(Opts::getOpt("zabbix_id"), $i, $length, "Week_" . date("Y-m-d", $i));
                }
                if ($length==Monda::_1DAY && date("H",$i)==0) {
                    Tw::twCreate(Opts::getOpt("zabbix_id"), $i, $length, "Day_" . date("Y-m-d", $i));
                }
                if ($length==Monda::_1HOUR && date("i",$i)==0) {
                    Tw::twCreate(Opts::getOpt("zabbix_id"), $i, $length, "Hour_" . date("Y-m-d H", $i));
                }
            }
        }
        Monda::mcommit();
        CliDebug::warn("Done\n");
        Tw::twLoi();
        return(true);
    }

    static function twSearch() {
        $secondssql="";
        if (Opts::getOpt("window_ids")) {
            $widssql = sprintf("AND id IN (%s)", join(",", Opts::getOpt("window_ids")));
            $tmesql="";
        } else {
            $widssql = "AND true";
            $tmesql=sprintf("AND tfrom>='%s' AND (tfrom+seconds*interval '1 second')<='%s'", New DateTime("@".Opts::getOpt("start")), New DateTime("@".Opts::getOpt("end")));
            if (Opts::getOpt("window_length")) {
                if (!is_array(Opts::getOpt("window_length"))) {
                    $secondssql="AND seconds=".Opts::getOpt("window_length");
                } else {
                    $secondssql="AND seconds IN (".join(",",Opts::getOpt("window_length")).") ";
                }
            }
        }
        $onlyemptysql = "";
        if (Opts::getOpt("window_empty")) {
            $onlyemptysql = "(updated IS NULL OR COUNT(itemstat.itemid)=0) AND";
            $loisql="";
        }
        if (preg_match("#/#", Opts::getOpt("window_sort"))) {
            List($sc, $so) = preg_split("#/#", Opts::getOpt("window_sort"));
        } else {
            $sc = Opts::getOpt("window_sort");
            $so = "+";
        }
        switch ($sc) {
            case "random":
                $sortsql = "RANDOM()";
                break;
            case "start":
                $sortsql = "tfrom";
                break;
            case "length":
                $sortsql = "seconds";
                break;
            case "loi":
                $sortsql = "timewindow.loi";
                break;
            case "loih":
                $sortsql = "timewindow.loi/(seconds/3600)";
                break;
            case "updated":
                $sortsql = "updated";
                break;
            default:
                $sortsql = "id";
        }
        if ($so == "-") {
            $sortsql.=" DESC";
        }
        if (Opts::getOpt("max_rows")) {
            $limit = "LIMIT " . Opts::getOpt("max_rows");
        } else {
            $limit = "";
        }
        $rows = Monda::mquery("
            SELECT 
                id,parentid,
                timewindow.loi,
                timewindow.loi/(timewindow.seconds/3600)::float AS loih,
                description,
                seconds,
                tfrom,
                (tfrom+seconds*interval '1 second') AS tto,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                created,
                updated,
                found,
                processed,
                ignored,
                lowstddev,
                lowavg,
                lowcnt,
                lowcv,
                timewindow.avgcv AS cv,
                timewindow.avgcnt AS cnt,
                serverid,     
                COUNT(itemstat.itemid) AS itemcount
            FROM timewindow
            LEFT JOIN itemstat ON (windowid=id)
            WHERE (
                serverid=?
                $secondssql
                $tmesql
                $widssql
                AND timewindow.loi>?
               )
            GROUP BY id
            HAVING $onlyemptysql true
            ORDER BY $sortsql
                $limit
                ", Opts::getOpt("zabbix_id"),Opts::getOpt("tw_minloi"));
        return($rows);
    }

    static function twSearchClock($clock,$empty=true,$toclock=false) {
        if ($empty) {
            $emptysql="found>0";
        } else {
            $emptysql="true";
        }
        if (!$toclock) $toclock=$clock;
        $rows = Monda::mquery("
            SELECT 
                id,parentid,
                tfrom,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                seconds,
                description,
                loi,
                loi/(seconds/3600) AS loih,
                created,
                updated,
                found,
                processed,
                ignored,
                lowstddev,
                lowavg,
                lowcnt,
                timewindow.avgcv AS cv,
                timewindow.avgcnt AS cnt,
                serverid
            FROM timewindow
            WHERE extract(epoch from tfrom)>=? AND extract(epoch from tfrom)+seconds<=? AND $emptysql AND serverid=?
            ", $clock, $toclock,Opts::getOpt("zabbix_id"));
        return($rows);
    }

    static function twGet($wid,$arr=false) {
        if (sizeof($wid)==0) {
            \App\Presenters\BasePresenter::mexit(10,"No windows to process!\n");
        }
        $id = Monda::mcquery("
            SELECT id,parentid,
                tfrom,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                seconds,
                description,
                timewindow.loi,
                created,
                updated,
                found,
                processed,
                ignored,
                lowstddev,
                lowavg,
                lowcnt,
                serverid,
                timewindow.avgcv AS cv,
                timewindow.avgcnt AS cnt,
                timewindow.loi/(timewindow.seconds/3600) AS loih,
                COUNT(itemstat.itemid) AS itemcount
             FROM timewindow
             LEFT JOIN itemstat ON (windowid=id)
             WHERE id IN (?) AND serverid=?
             GROUP BY timewindow.id", $wid, Opts::getOpt("zabbix_id"));
        if (count($id) == 1 && !$arr) {
            $id = $id[0];
        }
        return($id);
    }

    static function twToIds($clockonly=false) {
        if (!$clockonly) {
            $widrows = Tw::twSearch();
        } else {
            $widrows = Tw::twSearchClock(Opts::getOpt("start"),false,Opts::getOpt("end"));
        }
        $wids = Array();
        while ($wid = $widrows->fetch()) {
            $wids[] = $wid->id;
        }
        return($wids);
    }

    static function twStats($zabbix=false) {
        $wids = self::twToIds();
        if (!$wids) return(false);
        $row = Monda::mcquery("
            SELECT
                COUNT(*) AS cnt,
                MIN(tfrom) AS mintfrom,
                MAX(tfrom) AS maxtfrom,
                extract(epoch from MIN(tfrom)) AS minfstamp,
                extract(epoch from MAX(tfrom)) AS maxfstamp,
                extract(epoch from MAX(tfrom + (seconds||' seconds')::INTERVAL)) AS maxtstamp,
                MIN(seconds) AS minlength,
                MAX(seconds) AS maxlength,
                MIN(found) AS minfound,
                MAX(found) AS maxfound,
                MIN(lowstddev) AS minstddev,
                MAX(lowstddev) AS maxstddev,
                MIN(processed) AS minprocessed,
                MAX(processed) AS maxprocessed,
                MIN(ignored) AS minignored,
                MAX(ignored) AS maxignored,
                MIN(loi) AS minloi,
                MAX(loi) AS maxloi,
                MIN(loi::float/(seconds/3600)) AS minloih,
                MAX(loi::float/(seconds/3600)) AS maxloih,
                STDDEV(loi) AS stddevloi
            FROM timewindow
            WHERE id IN (?) AND serverid=?", $wids,Opts::getOpt("zabbix_id"));
        if (!$zabbix) {
            return($row[0]);
        } else {
            $fstamp = $row[0]->minfstamp;
            $tstamp = $row[0]->maxtstamp;
            $zrows = Monda::zcquery("
            SELECT DISTINCT ceil(clock/3600)*3600 AS cclock,
                COUNT(clock) AS cnt
            FROM history WHERE clock>? and clock<?
            GROUP BY ceil(clock/3600)*3600
            UNION
            SELECT DISTINCT ceil(clock/3600)*3600 AS cclock,
               COUNT(clock) AS cnt
            FROM history_uint WHERE clock>? and clock<?
            GROUP BY ceil(clock/3600)*3600", $fstamp, $tstamp, $fstamp, $tstamp);
            return($zrows);
        }
    }

    static function twZstats() {
        return(self::twStats(true));
    }

    static function TwTree($twids) {
        if (count($twids) == 0) {
            return(false);
        }
        $result = self::mcquery(
                        "SELECT
            tw1.id AS id1, tw1.seconds AS seconds1,
            tw2.id AS id2, tw2.seconds AS seconds2,
            tw3.id AS id3, tw3.seconds AS seconds3,
            tw4.id AS id4, tw4.seconds AS seconds4,
            tw5.id AS id5, tw5.seconds AS seconds5,
            tw6.id AS id6, tw6.seconds AS seconds6
            FROM timewindow tw1
            LEFT JOIN timewindow tw2 ON (tw2.parentid=tw1.id)
            LEFT JOIN timewindow tw3 ON (tw3.parentid=tw2.id)
            LEFT JOIN timewindow tw4 ON (tw4.parentid=tw3.id)
            LEFT JOIN timewindow tw5 ON (tw5.parentid=tw4.id)
            LEFT JOIN timewindow tw6 ON (tw6.parentid=tw5.id)
            WHERE tw2.id IN (?) OR tw3.id IN (?) OR tw4.id IN (?) OR tw5.id IN (?)
            AND tw1.serverid=? AND tw2.serverid=? AND tw3.serverid=? AND tw4.serverid=?
            ORDER BY tw1.tfrom,tw2.tfrom,tw3.tfrom,tw4.tfrom", $twids, $twids, $twids, $twids, Opts::getOpt("zabbix_id"), Opts::getOpt("zid"), Opts::getOpt("zid"), Opts::getOpt("zid"));
        $treeids = Array();
        foreach ($result as $row) {
            if ($row->id4) {
                $tree[$row->id1][$row->id2][$row->id3][$row->id4] = $row->id4;
                $treeids[$row->id1] = true;
                $treeids[$row->id2] = true;
                $treeids[$row->id3] = true;
                $treeids[$row->id4] = true;
            } elseif ($row->id3 && !array_key_exists($row->id3, $treeids)) {
                $tree[$row->id1][$row->id2][$row->id3] = $row->id3;
                $treeids[$row->id1] = true;
                $treeids[$row->id2] = true;
                $treeids[$row->id3] = true;
            } elseif ($row->id2 && !array_key_exists($row->id2, $treeids)) {
                $tree[$row->id1][$row->id2] = $row->id2;
                $treeids[$row->id1] = true;
                $treeids[$row->id2] = true;
            } elseif ($row->id1 && !array_key_exists($row->id1, $treeids)) {
                $tree[$row->id1] = $row->id1;
                $treeids[$row->id1] = true;
            }
        }
        return($tree);
    }

    static function twLoi() {
        Opts::setOpt("window_empty",false,"monda");
        Opts::setOpt("tw_minloi",-1,"monda");
        Opts::pushOpt("max_rows",Monda::_MAX_ROWS,"monda");
        $wids = self::twToIds();
        CliDebug::warn(sprintf("Recomputing loi for %d windows...", count($wids)));
        if (count($wids) == 0) {
            return(false);
        }
        Monda::mbegin();
        $uloi = Monda::mquery("
            UPDATE timewindow twchild
            SET loi=round(avgcnt*avgcv*(processed::float/found::float)),
            parentid=( SELECT id from timewindow twparent
              WHERE twchild.tfrom>=twparent.tfrom
              AND (extract(epoch from twchild.tfrom)+twchild.seconds)<=(extract(epoch from twparent.tfrom)+twparent.seconds)
              AND twchild.seconds<twparent.seconds
              ORDER BY seconds
              LIMIT 1 )
            WHERE twchild.id IN (?) AND found>0 AND serverid=?
            ", $wids,Opts::getOpt("zabbix_id"));
        $uloi2= Monda::mquery("
            UPDATE timewindow SET loi=0 WHERE found=0 OR found IS NULL AND serverid=?
                ",Opts::getOpt("zabbix_id"));
        Monda::mcommit();
        CliDebug::warn("Done\n");
        Opts::popOpt("max_rows");
    }

    static function twDelete() {
        Monda::mbegin();
        Opts::pushOpt("max_rows",Monda::_MAX_ROWS);
        $wids = self::twToIds(true);
        if (is_array(Opts::getOpt("window_length"))) {
            $lengths = join(",", Opts::getOpt("window_length"));
        } else {
            $lengths="All";
        }
        CliDebug::warn(sprintf("Deleting timewindows for zabbix_id %d from %s to %s, length %s (%d windows)...", Opts::getOpt("zabbix_id"), date("Y-m-d H:i", Opts::getOpt("start")), date("Y-m-d H:i", Opts::getOpt("end")), $lengths, count($wids)));
        if (count($wids) > 0) {
            $d1 = Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)", $wids);
            $d2 = Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)", $wids);
            $d3 = Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d4 = Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d5 = Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d6 = Monda::mquery("DELETE FROM timewindow WHERE id IN (?)", $wids);
        }
        CliDebug::warn("Done\n");
        Opts::popOpt("max_rows");
        return(Monda::mcommit());
    }

    function twMacrosExpand($w, $str) {
        $d = date_format(New DateTime(date("Y-m-d H:00", $w->fstamp)), "U");
        $replaces = Array(
            "%Y" => date("Y", $d),
            "%m" => date("m", $d),
            "%F" => date("F", $d),
            "%l" => date("l", $d),
            "%d" => date("d", $d),
            "%H" => date("H", $d),
            "%i" => date("i", $d),
        );
        return(str_replace(array_keys($replaces), $replaces, $str));
    }

    function twModify() {
        Monda::mbegin();
        Opts::pushOpt("max_rows",Monda::_MAX_ROWS);
        $wids = self::twToIds();
        foreach ($wids as $wid) {
            $w = Tw::twGet($wid);
            if (Opts::getOpt("chgloi")) {
                if (Opts::getOpt("chgloi")[0] == "+") {
                    $loi = $w->loi + Opts::getOpt("chgloi");
                } elseif (Opts::getOpt("chgloi")[0] == "-") {
                    $loi = $w->loi + Opts::getOpt("chgloi");
                } else {
                    $loi = Opts::getOpt("chgloi");
                }
            }

            if (Opts::getOpt("rename")) {
                $desc = self::twMacrosExpand($w, Opts::getOpt("rename"));
                Monda::mquery(
                        "UPDATE timewindow
                        SET description=? WHERE id=?", $desc, $w->id
                );
            }
        }
        Opts::popOpt("max_rows");
        return(Monda::mcommit());
    }

    function twEmpty() {
        Monda::mbegin();
        Opts::pushOpt("max_rows",Monda::_MAX_ROWS);
        $wids = self::twToIds();
        CliDebug::warn(sprintf("Emptying timewindows for zabbix_id %d from %s to %s, length %s (%d windows)...", Opts::getOpt("zabbix_id"), date("Y-m-d H:i", Opts::getOpt("start")), date("Y-m-d H:i", Opts::getOpt("end")), join(",", Opts::getOpt("window_length")), count($wids)));
        if (count($wids) > 0) {
            $d1 = Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)", $wids);
            $d2 = Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)", $wids);
            $d3 = Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d4 = Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d5 = Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d6 = Monda::mquery("UPDATE timewindow SET updated=?, processed=0,found=0,loi=0 WHERE id IN (?)", New DateTime(), $wids);
        }
        CliDebug::warn("Done\n");
        Opts::popOpt("max_rows");
        return(Monda::mcommit());
    }

}

?>
