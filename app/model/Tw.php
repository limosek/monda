<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * TimeWindow global class
 */
class Tw extends Monda {

    function twCreate($zid, $start, $length, $description) {

        $id = Monda::mquery("SELECT id FROM timewindow WHERE tfrom=? AND seconds IN (?) AND serverid=?", New DateTime("@$start"), $length, $zid
                )->fetch();
        if ($id == false) {
            CliDebug::warn("Creating window $start,$length,$description\n");
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
        } else {
            CliDebug::dbg("Skiping window zabbix_id=$zid,start=$start,length=$length,$description (already in db)\n");
        }
    }

    function twMultiCreate($opts) {
        if (!is_array($opts->length)) {
            return(false);
        }
        $fdate=date("Y-m-d",$opts->start);
        $tdate=date("Y-m-d",$opts->end);
        $lengths=join(",",$opts->length);
        CliDebug::warn("Creating windows from $fdate to $tdate and lengths $lengths\n");
        Monda::mbegin();
        $start=round($opts->start/3600,0)*3600;
        $end=(round($opts->end/3600,0)+1)*3600;
        for ($i = $start; $i < $end; $i = $i + Monda::_1HOUR) {
            foreach ($opts->length as $length) {
                if ($length==Monda::_1YEAR && date("m:d:H",$i)=="01:01:00") {
                    Tw::twCreate($this->opts->zid, $i, $length, "Year_" . date("Y", $i));
                }
                if ($length>=Monda::_1MONTH28 && $length<=Monda::_1MONTH31 && date("d:H",$i)=="01:00") {
                    $monthseconds = date("t", $i) * 3600 * 24;
                    Tw::twCreate($this->opts->zid, $i, $monthseconds, "Month_" . date("Y-m", $i));
                }
                if ($length==Monda::_1WEEK && date("N-H",$i)=="1-00") {
                    Tw::twCreate($this->opts->zid, $i, $length, "Week_" . date("Y-m-d", $i));
                }
                if ($length==Monda::_1DAY && date("H",$i)==0) {
                    Tw::twCreate($this->opts->zid, $i, $length, "Day_" . date("Y-m-d", $i));
                }
                if ($length==Monda::_1HOUR && date("i",$i)==0) {
                    Tw::twCreate($this->opts->zid, $i, $length, "Hour_" . date("Y-m-d H", $i));
                }
            }
        }
        Monda::mcommit();
        Tw::twLoi($opts);
        return(true);
    }

    function twSearch($opts) {
        $secondssql="";
        if ($opts->wids) {
            $widssql = sprintf("AND id IN (%s)", join(",", $opts->wids));
            $tmesql="";
        } else {
            $widssql = "AND true";
            $tmesql=sprintf("AND tfrom>='%s' AND (tfrom+seconds*interval '1 second')<='%s'", New DateTime("@$opts->start"), New DateTime("@$opts->end"));
            if ($opts->length) {
                if (!is_array($opts->length)) {
                    $secondssql="AND seconds=$opts->length ";
                } else {
                    $secondssql="AND seconds IN (".join(",",$opts->length).") ";
                }
            }
        }
        $onlyemptysql = "";
        if ($opts->empty) {
            $onlyemptysql = "(updated IS NULL OR COUNT(itemstat.itemid)=0) AND";
        } else {
            $onlyemptysql = "(updated IS NOT NULL AND COUNT(itemstat.itemid)>0) AND";
        }
        if (preg_match("#/#", $opts->wsort)) {
            List($sc, $so) = preg_split("#/#", $opts->wsort);
        } else {
            $sc = $opts->wsort;
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

        $updatedflag = "true";
        if (is_numeric($opts->updated)) {
            $updatedflag = false;
            $updated = $opts->updated;
        } elseif ($opts->updated == false) {
            $updatedflag = true;
            $updated = 0;
        } else {
            $updated = time();
        }
        if ($opts->createdonly) {
            $createdsql = "updated IS NULL";
        } else {
            $createdsql = "true";
        }
        if ($opts->max_rows) {
            $limit = "LIMIT " . $opts->max_rows;
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
                serverid,     
                COUNT(itemstat.itemid) AS itemcount
            FROM timewindow
            LEFT JOIN itemstat ON (windowid=id)
            WHERE (
                serverid=?
                $secondssql
                $tmesql
                AND timewindow.loi>$opts->minloi 
                AND (updated<? OR ?)
                AND $createdsql
                $widssql
               )
            GROUP BY id
            HAVING $onlyemptysql true
            ORDER BY $sortsql
                $limit
                ", $opts->zid, New DateTime("@" . $updated), $updatedflag
        );
        return($rows);
    }

    function twSearchClock($clock,$empty=true,$toclock=false) {
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
                serverid
            FROM timewindow
            WHERE extract(epoch from tfrom)>=? AND extract(epoch from tfrom)+seconds<=? AND $emptysql
            ", $clock, $toclock);
        return($rows);
    }

    function twGet($wid,$arr=false) {
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
                timewindow.loi/(timewindow.seconds/3600) AS loih,
                COUNT(itemstat.itemid) AS itemcount
             FROM timewindow
             LEFT JOIN itemstat ON (windowid=id)
             WHERE id IN (?)
             GROUP BY timewindow.id", $wid);
        if (count($id) == 1 && !$arr) {
            $id = $id[0];
        }
        return($id);
    }

    function twToIds($opts,$clockonly=false) {
        if (!$clockonly) {
            $widrows = Tw::twSearch($opts);
        } else {
            $widrows = Tw::twSearchClock($opts->start,false,$opts->end);
        }
        $wids = Array();
        while ($wid = $widrows->fetch()) {
            $wids[] = $wid->id;
        }
        return($wids);
    }

    function twStats($opts,$zabbix=false) {
        $wids = self::twToIds($opts);
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
            WHERE id IN (?)", $wids);
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

    function twZstats($opts) {
        return(self::twStats($opts, true));
    }

    function TwTree($twids) {
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
            ORDER BY tw1.tfrom,tw2.tfrom,tw3.tfrom,tw4.tfrom", $twids, $twids, $twids, $twids);
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

    function twLoi($opts) {
        $opts->empty = false;
        $opts->updated = true;
        $opts->minloi = -1;
        $wids = self::twToIds($opts);
        CliDebug::warn(sprintf("Recomputing loi for %d windows\n", count($wids)));
        if (count($wids) == 0) {
            return(false);
        }
        Monda::mbegin();
        $uloi = Monda::mquery("
            UPDATE timewindow twchild
            SET loi=round(100*(processed::float/found::float)),
            parentid=( SELECT id from timewindow twparent
              WHERE twchild.tfrom>=twparent.tfrom
              AND (extract(epoch from twchild.tfrom)+twchild.seconds)<=(extract(epoch from twparent.tfrom)+twparent.seconds)
              AND twchild.seconds<twparent.seconds
              ORDER BY seconds
              LIMIT 1 )
            WHERE twchild.id IN (?) AND found>0
            ", $wids);
        $uloi2= Monda::mquery("
            UPDATE timewindow SET loi=0 WHERE found=0 OR found IS NULL
                ");
        Monda::mcommit();
    }

    function twDelete($opts) {
        Monda::mbegin();
        $wids = self::twToIds($opts,true);
        if (is_array($opts->length)) {
            $lengths = join(",", $opts->length);
        } else {
            $lengths="All";
        }
        CliDebug::warn(sprintf("Deleting timewindows for zabbix_id %d from %s to %s, length %s (%d windows)\n", $opts->zid, date("Y-m-d H:i", $opts->start), date("Y-m-d H:i", $opts->end), $lengths, count($wids)));
        if (count($wids) > 0) {
            $d1 = Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)", $wids);
            $d2 = Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)", $wids);
            $d3 = Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d4 = Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d5 = Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d6 = Monda::mquery("DELETE FROM timewindow WHERE id IN (?)", $wids);
        }
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

    function twModify($opts) {
        Monda::mbegin();
        $wids = self::twToIds($opts);
        foreach ($wids as $wid) {
            $w = Tw::twGet($wid);
            if ($opts->chgloi) {
                if ($opts->chgloi[0] == "+") {
                    $loi = $w->loi + $opts->chgloi;
                } elseif ($opts->chgloi[0] == "-") {
                    $loi = $w->loi + $opts->chgloi;
                } else {
                    $loi = $opts->chgloi;
                }
            }

            if ($opts->rename) {
                $desc = self::twMacrosExpand($w, $opts->rename);
                Monda::mquery(
                        "UPDATE timewindow
                        SET description=? WHERE id=?", $desc, $w->id
                );
            }
        }
        return(Monda::mcommit());
    }

    function twEmpty($opts) {
        Monda::mbegin();
        $wids = self::twToIds($opts);
        CliDebug::warn(sprintf("Emptying timewindows for zabbix_id %d from %s to %s, length %s (%d windows)\n", $opts->zid, date("Y-m-d H:i", $opts->start), date("Y-m-d H:i", $opts->end), join(",", $opts->length), count($wids)));
        if (count($wids) > 0) {
            $d1 = Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)", $wids);
            $d2 = Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)", $wids);
            $d3 = Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d4 = Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d5 = Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $wids, $wids);
            $d6 = Monda::mquery("UPDATE timewindow SET updated=?, processed=0,found=0,loi=0 WHERE id IN (?)", New DateTime(), $wids);
        }
        return(Monda::mcommit());
    }

}

?>
