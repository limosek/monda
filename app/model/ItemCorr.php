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
class ItemCorr extends Monda {
   
    function icSearch($opts) {
        if ($opts->itemids) {
            $itemidssql=sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND",join(",",$opts->itemids),join(",",$opts->itemids));
        } else {
            $itemidssql="";
        }
        if ($opts->hostids) {
            $hostidssql=sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND",join(",",$opts->hostids),join(",",$opts->hostids));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds($opts);
        if (count($wids)>0) {
            $windowidsql=sprintf("is1.windowid IN (%s) AND is2.windowid IN (%s) AND",join(",",$wids),join(",",$wids));
        } else {
            return(false);
        }
        if ($opts->icempty) {
            $emptysql="AND (ic.itemid1 IS NULL OR ic.itemid2 IS NULL)";
        } else {
            $emptysql="AND (ic.itemid1 IS NOT NULL AND ic.itemid2 IS NOT NULL)";
        }
        if ($opts->icloionly) {
            $loisql="AND ic.loi>0";
        } else {
            $loisql="";
        }
        switch ($opts->corr) {
            case "samewindow":
                $join1sql="is1.windowid=is2.windowid";
                $sameiwsql="AND is1.itemid<>is2.itemid";
                break;
            case "samehour":
                $join1sql="is1.itemid=is2.itemid";
                $sameiwsql="AND tw1.seconds=3600 AND is1.windowid<>is2.windowid AND extract(hour from tw1.tfrom)=extract(hour from tw2.tfrom)";
                break;
            case "samedow":
                $join1sql="is1.itemid=is2.itemid";
                $sameiwsql="AND tw1.seconds=68400 AND is1.windowid<>is2.windowid AND extract(dow from tw1.tfrom)=extract(dow from tw2.tfrom)";
                break;              
        }
        $rows=self::mquery(
                "SELECT
                        is1.itemid AS itemid1,is2.itemid AS itemid2,
                        is1.windowid AS windowid1, is2.windowid AS windowid2,
                        ic.itemid1 As icitemid1,ic.itemid2 AS icitemid2,
                        ic.windowid1 AS icwindowid1,ic.windowid2 AS icwindowid2,
                        tw1.tfrom AS tfrom1, tw1.seconds AS seconds1,
                        tw2.tfrom AS tfrom2, tw2.seconds AS seconds2,
                        ic.corr AS corr, ic.loi AS icloi
                 FROM itemstat is1
                 JOIN itemstat is2 ON ($join1sql)
                 LEFT JOIN itemcorr ic ON (is1.itemid=itemid1 AND itemid2=is2.itemid AND is1.windowid=windowid1 AND is2.windowid=windowid2)
                 JOIN timewindow tw1 ON (is1.windowid=tw1.id)
                 JOIN timewindow tw2 ON (is2.windowid=tw2.id)
                 WHERE 
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                         true
                    AND tw1.seconds=tw2.seconds
                    AND (is1.windowid<=is2.windowid)
                    AND (is1.itemid<=is2.itemid)
                    $emptysql $sameiwsql $loisql
                 ORDER BY ic.loi DESC, (is1.loi+is2.loi) DESC, ic.windowid1,ic.windowid2,ic.itemid1,ic.itemid2
                 LIMIT ?",$opts->maxicrows
                );
        return($rows);
    }
    
    function icToIds($opts,$pkey=false) {
        $ids=self::icSearch($opts);
        if (!$ids) {
            return(false);
        }
        $rows=$ids->fetchAll();
        $icids=Array();
        foreach ($rows as $row) {
            if ($pkey) {
                $icids[]=Array(
                    "itemid1" => $row->itemid1,
                    "itemid2" => $row->itemid2,
                    "windowid1" => $row->windowid1,
                    "windowid2" => $row->windowid2,
                    "tfrom1" => date_format($row->tfrom1,"U"),
                    "tto1" => date_format($row->tfrom1,"U")+$row->seconds1,
                    "tfrom2" => date_format($row->tfrom2,"U"),
                    "tto2" => date_format($row->tfrom2,"U")+$row->seconds2,
                      );
                } else {
                    $icids[]=$row->itemid1;
                }
        }
        return($icids);
    }
    
    function icMultiCompute($opts) {
        $opts->icempty = true;
        $cids = self::icToIds($opts, true);
        if (count($cids) == 0 || !$cids) {
            return(false);
        }
        $wids=self::extractIds($cids,array("windowid1","windowid2","itemid1","itemid2"));
        CliDebug::warn(sprintf("Need to compute correlations for %d combinations of %dx%d items (mode %s,seconds=%s).\n", count($cids), count($wids["itemid1"]),count($wids["itemid2"]),$opts->corr,join(",",$opts->length)));
        $i = 0;
        foreach ($wids["windowid1"] as $wid1) {
            $i++;
            $w1=Tw::twGet($wid1)->fetch();
            if (self::doJob()) {
                CliDebug::info(sprintf("Computing correlations for window %d (%d of %d)\n", $wid1,$i,count($wids["windowid1"])));
                $j=0;
                foreach ($wids["windowid2"] as $wid2) {
                    if (!self::IdsSearch(
                            Array(
                                "windowid1" => $wid1,
                                "windowid2" => $wid2,
                                ),$cids)) {
                        continue;
                    }
                    $j++;
                    //CliDebug::info(sprintf("Computing correlations for windows %d-%d (%d of %d)\n", $wid1,$wid2,$j,count($wids["windowid2"])));
                    $w2=Tw::twGet($wid2)->fetch();
                    self::mbegin();
                    $icrows = self::zcquery("
                    SELECT  h1.itemid AS itemid1,
                            h2.itemid AS itemid2,
                            COUNT(*) AS cnt,
                            CORR(h1.value,h2.value) AS corr
                    FROM history h1
                    JOIN history h2 ON (ABS(h1.clock-h2.clock+(?))<? AND h2.itemid IN (?))
                    WHERE h1.itemid IN (?)
                        AND h1.clock>? AND h1.clock<?
                        AND h2.clock>? AND h2.clock<?
                    GROUP BY h1.itemid,h2.itemid
                    HAVING COUNT(*)>?

                     UNION

                     SELECT  h1.itemid AS itemid1,
                            h2.itemid AS itemid2,
                            COUNT(*) AS cnt,
                            CORR(h1.value,h2.value) AS corr
                    FROM history_uint h1
                    JOIN history_uint h2 ON (ABS(h1.clock-h2.clock+(?))<? AND h2.itemid IN (?))
                    WHERE h1.itemid IN (?)
                        AND h1.clock>? AND h1.clock<?
                        AND h2.clock>? AND h2.clock<?
                    GROUP BY h1.itemid,h2.itemid
                    HAVING COUNT(*)>?
                    ",      $w2["fstamp"]-$w1["fstamp"],
                            $opts->timeprecision,
                            $wids["itemid1"],$wids["itemid2"], 
                            $w1["fstamp"], $w1["tstamp"],
                            $w2["fstamp"], $w2["tstamp"],
                            $opts->min_icvalues,
                            $w2["fstamp"]-$w1["fstamp"],
                            $opts->timeprecision,
                            $wids["itemid1"],$wids["itemid2"], 
                            $w1["fstamp"], $w1["tstamp"],
                            $w2["fstamp"], $w2["tstamp"],
                            $opts->min_icvalues
                    );
                    $mincorr = 0;
                    $maxcorr = 0;
                    foreach ($icrows as $icrow) {
                        $dic = self::mquery("
                        DELETE FROM itemcorr
                        WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?",
                                $wid1, $wid2, $icrow->itemid1, $icrow->itemid2);
                        $icrow->windowid1 = $wid1;
                        $icrow->windowid2 = $wid2;
                        if ($icrow->corr === null || $icrow->cnt<$opts->min_icvalues) {
                            $icrow->corr = 0;
                        }
                        if (abs($icrow->corr)>1) {
                            CliDebug::warn(sprintf("Bad correlation %dx%d=%f! Ignoring.\n",$icrow->itemid1,$icrow->itemid2,$icrow->corr));
                            continue;
                        }
                        $mincorr = min($mincorr, abs($icrow->corr));
                        $maxcorr = min($maxcorr, abs($icrow->corr));
                        $iic = self::mquery("INSERT INTO itemcorr ", $icrow);
                    }
                    self::mcommit();
                    CliDebug::info(sprintf("Min corr: %f, max corr: %f\n", $mincorr, $maxcorr));
                }
                self::exitJob();
            }
        }
        self::exitJobServer();
    }
    
    function icDelete($opts) {
        $opts->maxicrows=100000;
        $rows=self::icToIds($opts,true);
        CliDebug::warn(sprintf("Will delete %d item correlations.\n",count($rows)));
        if (count($rows)==0) {
            return(false);
        }
        self::mbegin();
        foreach ($rows as $row) {
            $row2=Array(
                "windowid1" => $row["windowid1"],
                "windowid2" => $row["windowid2"],
                "itemid1" => $row["itemid1"],
                "itemid2" => $row["itemid2"],
            );
            self::mquery("DELETE FROM itemcorr WHERE ?",$row2);
        }
        self::mcommit();
    }
    
    function icLoi($opts) {
        $twids=  Tw::twToIds($opts);
        if (count($twids)==0) {
            return(false);
        }
        Monda::mbegin();
        $lsql=Monda::mquery("
            UPDATE itemcorr
            SET loi=ABS(corr)*100
            WHERE windowid1 IN (?) AND windowid2 IN (?)
            ",$twids,$twids);
        Monda::mcommit();
    }
}

?>
