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
        $itemids=ItemStat::isToIds($opts);
        if (count($itemids)>0) {
            $itemidssql=sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND",join(",",$itemids),join(",",$itemids));
        } else {
            $itemidssql="";
        }
        if (!isset($opts->nosort)) {
           $ranksql1=",RANK() OVER (PARTITION BY is1.itemid ORDER BY is1.loi DESC)+RANK() OVER (PARTITION BY is1.itemid ORDER BY is2.loi DESC) AS rank";
           $ranksql2="ORDER BY (tw1.loi+tw2.loi), rank, (ic.itemid1 IS NULL OR ic.itemid2 IS NULL) DESC";
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
            //$emptysql="AND (ic.itemid1 IS NULL OR ic.itemid2 IS NULL)";
            $emptysql="";
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
                $join1sql="is1.windowid=is2.windowid AND is1.itemid<>is2.itemid";
                $sameiwsql="AND is1.itemid<>is2.itemid
                            AND is1.windowid=is2.windowid";
                break;
            case "samehour":
                $join1sql="is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql="AND is1.itemid=is2.itemid
                            AND tw1.seconds=3600
                            AND is1.windowid<>is2.windowid
                            AND extract(hour from tw1.tfrom)=extract(hour from tw2.tfrom)";
                break;
            case "samedow":
                $join1sql="is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql="AND is1.itemid=is2.itemid
                            AND tw1.seconds=86400
                            AND is1.windowid<>is2.windowid
                            AND extract(dow from tw1.tfrom)=extract(dow from tw2.tfrom)";
                break;              
        }
        $rows=self::mquery(
                "SELECT
                        is1.itemid AS itemid1,is2.itemid AS itemid2,
                        is1.windowid AS windowid1, is2.windowid AS windowid2,
                        is1.loi AS item1loi, is2.loi AS item2loi,
                        tw1.loi AS tw1loi, tw2.loi AS tw2loi,
                        ic.itemid1 As icitemid1,ic.itemid2 AS icitemid2,
                        ic.windowid1 AS icwindowid1,ic.windowid2 AS icwindowid2,
                        tw1.tfrom AS tfrom1, tw1.seconds AS seconds1,
                        tw2.tfrom AS tfrom2, tw2.seconds AS seconds2,
                        ic.corr AS corr, ic.loi AS icloi
                        $ranksql1
                 FROM itemstat is1
                 JOIN itemstat is2 ON ($join1sql)
                 LEFT JOIN itemcorr ic ON (is1.itemid=itemid1 AND itemid2=is2.itemid AND is1.windowid=windowid1 AND is2.windowid=windowid2)
                 JOIN timewindow tw1 ON (is1.windowid=tw1.id)
                 JOIN timewindow tw2 ON (is2.windowid=tw2.id)
                 WHERE 
                    ((ic.corr>? AND ic.corr<?) OR ic.corr IS NULL) AND
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                         true
                    AND tw1.seconds=tw2.seconds
                    AND (is1.windowid<=is2.windowid)
                    AND (is1.itemid<=is2.itemid)
                    $sameiwsql $loisql $emptysql
                 $ranksql2
                 LIMIT ?",$opts->min_corr,$opts->max_corr,$opts->max_rows
                );
        return($rows);
    }
    
    function icQuickSearch($opts) {
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
        switch ($opts->corr) {
            case "samewindow":
                $corrsql="AND ic.windowid1=ic.windowid2";
                break;
            case "samehour":
                $opts->length=Array(Monda::_1HOUR);
                $corrsql="AND ic.windowid1<>ic.windowid2";
                break;
            case "samedow":
                $opts->length=Array(Monda::_1DAY);
                $corrsql="AND ic.windowid1<>ic.windowid2";
                break;              
        }
        if (!$opts->wids) {
            $wids=Tw::twToIds($opts);
        } else {
            $wids=$opts->wids;
        }
        if (count($wids)>0) {
            $windowidsql=sprintf("is1.windowid IN (%s) AND is2.windowid IN (%s) AND",join(",",$wids),join(",",$wids));
        } else {
            return(false);
        }
        if ($opts->icloionly) {
            $loisql="AND ic.loi>0";
        } else {
            $loisql="";
        }
        $rows=self::mquery(
                "SELECT
                        windowid1,windowid2,itemid1,itemid2,corr,ic.cnt,ic.loi AS icloi
                 FROM itemcorr ic
                 JOIN itemstat is1 ON (ic.itemid1=is1.itemid AND ic.windowid1=is1.windowid)
                 JOIN itemstat is2 ON (ic.itemid2=is2.itemid AND ic.windowid2=is2.windowid)
                 WHERE 
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                         true
                     $loisql
                     $corrsql
                     AND ic.corr>? AND ic.corr<? 
                 ORDER BY ic.loi DESC
                 LIMIT ?
                ",$opts->min_corr,$opts->max_corr,$opts->max_rows);
        return($rows);
    }
    
    function icToIds($opts,$pkey=false,$quick=false) {
        if ($quick) {
            $ids=self::icQuickSearch($opts); 
        } else {
            $ids=self::icSearch($opts);
        }
        if (!$ids) {
            return(false);
        }
        $rows=$ids->fetchAll();
        $icids=Array();
        foreach ($rows as $row) {
            if ($pkey) {
                if ($opts->icempty) {
                    if ($row->icitemid1!=null && $row->icitemid2!=null) {
                        continue;
                    }
                }
                $icids[]=Array(
                    "itemid1" => $row->itemid1,
                    "itemid2" => $row->itemid2,
                    "windowid1" => $row->windowid1,
                    "windowid2" => $row->windowid2,
                    "icitemid1" => $row->icitemid1,
                    "icitemid2" => $row->icitemid2,
                    "tfrom1" => date_format($row->tfrom1,"U"),
                    "tto1" => date_format($row->tfrom1,"U")+$row->seconds1,
                    "tfrom2" => date_format($row->tfrom2,"U"),
                    "tto2" => date_format($row->tfrom2,"U")+$row->seconds2,
                      );
                } else {
                    $icids[$row->itemid1]=$row->itemid1;
                    $icids[$row->itemid2]=$row->itemid2;
                }
        }
        return($icids);
    }
    
    function icStats($opts) {
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
        $rows=self::mquery(
                "SELECT
                        COUNT(DISTINCT windowid1) AS wcnt1,COUNT(DISTINCT windowid2) AS wcnt2,
                        itemid1,itemid2,
                        AVG(ic.corr)*COUNT(DISTINCT windowid1)*COUNT(DISTINCT windowid2) AS wcorr,
                        AVG(corr) AS acorr,
                        AVG(ic.cnt) AS acnt,
                        AVG(ic.loi) AS aloi
                 FROM itemcorr ic
                 JOIN itemstat is1 ON (ic.itemid1=is1.itemid AND ic.windowid1=is1.windowid)
                 JOIN itemstat is2 ON (ic.itemid2=is2.itemid AND ic.windowid2=is2.windowid)
                 WHERE 
                    (
                     (itemid1<>itemid2) AND (windowid1=windowid2)
                     OR
                     (itemid1=itemid2) AND (windowid1<>windowid2)
                    ) AND
                    ic.corr>? AND ic.corr<?
                    AND
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                    true
                 GROUP BY itemid1,itemid2
                 ORDER BY AVG(ic.corr) DESC
                 LIMIT ?
                ",$opts->min_corr,$opts->max_corr,$opts->max_rows);
        return($rows);
    }
    
    function icWStats($opts) {
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
        $rows=self::mquery(
                "SELECT
                        COUNT(DISTINCT itemid1) AS icnt1,COUNT(DISTINCT itemid2) AS icnt2,
                        windowid1,windowid2,
                        AVG(ic.corr)*COUNT(DISTINCT itemid1)*COUNT(DISTINCT itemid2) AS icorr,
                        AVG(corr) AS acorr,
                        AVG(ic.cnt) AS acnt,
                        AVG(ic.loi) AS aloi
                 FROM itemcorr ic
                 JOIN itemstat is1 ON (ic.itemid1=is1.itemid AND ic.windowid1=is1.windowid)
                 JOIN itemstat is2 ON (ic.itemid2=is2.itemid AND ic.windowid2=is2.windowid)
                 WHERE 
                    (
                     (itemid1=itemid2) AND (windowid1<>windowid2)
                    ) AND
                    ((ic.corr>? AND ic.corr<?) OR ic.corr IS NULL)
                    AND
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                    true
                 GROUP BY windowid1,windowid2
                 ORDER BY AVG(ic.corr) DESC
                 LIMIT ?
                ",$opts->min_corr,$opts->max_corr,$opts->max_rows);
        return($rows);
    }
    
    function icMultiCompute($opts) {
        $opts->icempty = true;
        $cids = self::icToIds($opts, true);
        if (!$cids) {
            return(false);
        }
        $wids=self::extractIds($cids,array("windowid1","windowid2","itemid1","itemid2","icitemid1","icitemid2"));
        CliDebug::warn(sprintf("Need to look on correlations for %d combinations (%dx%d) items. %dx%d items needs to be computed. (mode %s,seconds=%s).\n",
                count($cids),
                count($wids["itemid1"]),
                count($wids["itemid2"]),
                count($wids["itemid1"])-count($wids["icitemid1"]),
                count($wids["itemid2"])-count($wids["icitemid2"]),
                $opts->corr,
                join(",",$opts->length)));
        if (count($cids)==0) {
            return(false);
        }
        $i = 0;
        foreach ($wids["windowid1"] as $wid1) {
            $i++;
            $w1=Tw::twGet($wid1);
            if (self::doJob()) {
                //CliDebug::info(sprintf("Computing correlations for window %d (%d of %d)\n", $wid1,$i,count($wids["windowid1"])));
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
                    CliDebug::info(sprintf("Computing correlations for windows %d-%d\n", $wid1,$wid2));
                    $w2=Tw::twGet($wid2);
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
                    HAVING COUNT(*)>=?
                    
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
                    GROUP BY h1.itemid, h2.itemid
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
                    self::mbegin();
                    foreach ($icrows as $icrow) {
                        
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
                        $maxcorr = max($maxcorr, abs($icrow->corr));
                        $wrow=Array(
                            "windowid1" => $icrow->windowid1,
                            "windowid2" => $icrow->windowid2,
                            "itemid1" => $icrow->itemid1,
                            "itemid2" => $icrow->itemid2,
                            "corr" => $icrow->corr,
                            "cnt" => $icrow->cnt
                        );
                        self::mquery("DELETE FROM itemcorr WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?",$icrow->windowid1,$icrow->windowid2,$icrow->itemid1,$icrow->itemid2);
                        $iic = self::mquery("INSERT INTO itemcorr", $wrow);
                    }
                    CliDebug::info(sprintf("Min corr: %f, max corr: %f\n", $mincorr, $maxcorr));
                    self::mcommit();
                }
                self::exitJob();
            }
        }
        self::exitJobServer();
    }
    
    function icDelete($opts) {
        $windows=Tw::twToIds($opts);
        CliDebug::warn(sprintf("Will delete item correlations from %d windows.\n",count($windows)));
        if (count($windows)==0) {
            return(false);
        }
        self::mbegin();
        self::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$windows,$windows);
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
