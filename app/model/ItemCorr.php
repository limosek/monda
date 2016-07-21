<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    App\Model\Opts,
    App\Model\ItemStat,
    App\Model\Tw,
    Nette\Security\Passwords,
    Tracy\Debugger,
    Nette\Database\Context,
    \Exception;

/**
 * ItemCorr global class
 * It computes ans searches correlations between items
 */
class ItemCorr extends Monda {

    /**
    * Search items which were preprocessed to compute item correlations
    * @return Nette\Database\Result
    * @throws Exception
    */
    static function icSearchItemsToIc() {
        $itemids=ItemStat::isToIds();
        if (count($itemids)>0) {
            $itemidssql=sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND",join(",",$itemids),join(",",$itemids));
        } else {
            $itemidssql="";
        }
        if (Opts::getOpt("hostids")) {
            $hostidssql=sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND",join(",",Opts::getOpt("hostids")),join(",",Opts::getOpt("hostids")));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds();
        if (count($wids)>0) {
            $windowidsql=sprintf("is1.windowid IN (%s) AND is2.windowid IN (%s) AND",join(",",$wids),join(",",$wids));
        } else {
            throw New Exception("No windows matched query.");
        }
        
        if (preg_match("#/#", Opts::getOpt("ic_sort"))) {
            List($sc, $so) = preg_split("#/#", Opts::getOpt("ic_sort"));
        } else {
            $sc = Opts::getOpt("ic_sort");
            $so = "+";
        }
        switch ($sc) {
            case "start":
                $sortsql = "tw1.tfrom,is1.itemid,tw2.tfrom,is2.itemid";
                break;
            case "loi":
                $sortsql = "(tw1.loi+tw2.loi+is1.loi+is2.loi)";
                break;
            case "id":
                $sortsql = "is1.itemid,is2.itemid";
                break;
        }
        if ($so == "-") {
            $sortsql.=" DESC";
        }
        
        switch (Opts::getOpt("corr_type")) {
            case "samewindow":
                $join1sql="is1.windowid=is2.windowid AND is1.itemid<>is2.itemid";
                $sameiwsql="AND is1.itemid<>is2.itemid
                            AND is1.windowid=is2.windowid";
                break;
            case "samehour":
                $join1sql="is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql="AND is1.itemid=is2.itemid
                            AND tw1.seconds=".Monda::_1HOUR."
                            AND is1.windowid<>is2.windowid
                            AND extract(hour from tw1.tfrom)=extract(hour from tw2.tfrom)";
                break;
            case "samedow":
                $join1sql="is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql="AND is1.itemid=is2.itemid
                            AND tw1.seconds=".Monda::_1DAY."
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
                        tw1.tfrom AS tfrom1, tw1.seconds AS seconds1,
                        tw2.tfrom AS tfrom2, tw2.seconds AS seconds2,
                        ic.corr AS corr
                 FROM itemstat is1
                 JOIN itemstat is2 ON ($join1sql)
                 JOIN timewindow tw1 ON (is1.windowid=tw1.id)
                 JOIN timewindow tw2 ON (is2.windowid=tw2.id)
                 LEFT JOIN itemcorr ic ON 
                        (ic.itemid1=is1.itemid AND ic.itemid2=is2.itemid
                        AND ic.windowid1=is1.windowid AND ic.windowid2=is2.windowid)
                 WHERE 
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                         true
                    AND tw1.seconds=tw2.seconds
                    AND (is1.windowid<=is2.windowid)
                    AND (is1.itemid<=is2.itemid)
                    $sameiwsql
                    AND is1.loi>? AND is2.loi>?
                    AND ic.corr IS NULL
                 ORDER BY $sortsql
                 LIMIT ?",Opts::getOpt("is_minloi"),Opts::getOpt("is_minloi"),Opts::getOpt("max_rows")
                );
        return($rows);
    }
    
    /**
    * Search computed item correlations
    * @return Nette\Database\Result
    * @throws Exception
    */
    static function icSearch() {
        if (Opts::getOpt("itemids")) {
            if (sizeof(Opts::getOpt("itemids")==1)) {
                $itemidssql=sprintf("(is1.itemid IN (%s) OR is2.itemid IN (%s)) AND",join(",",Opts::getOpt("itemids")),join(",",Opts::getOpt("itemids")));
            } else {
                $itemidssql=sprintf("(is1.itemid IN (%s) AND is2.itemid IN (%s)) AND",join(",",Opts::getOpt("itemids")),join(",",Opts::getOpt("itemids")));
            }
        } else {
            $itemidssql="";
        }
        if (Opts::getOpt("hostids")) {
            $hostidssql=sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND",join(",",Opts::getOpt("hostids")),join(",",Opts::getOpt("hostids")));
        } else {
            $hostidssql="";
        }
        switch (Opts::getOpt("corr_type")) {
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
        if (preg_match("#/#", Opts::getOpt("ic_sort"))) {
            List($sc, $so) = preg_split("#/#", Opts::getOpt("ic_sort"));
        } else {
            $sc = Opts::getOpt("ic_sort");
            $so = "+";
        }
        switch ($sc) {
            case "start":
                $sortsql = "tw.tfrom";
                break;
            case "loi":
                $sortsql = "ic.loi";
                break;
            case "isloi":
                $sortsql = "(is1.loi+is2.loi)";
                break;
            case "id":
                $sortsql = "is1.itemid,is2.itemid";
                break;
            default:
                $sortsql = "id";
        }
        if ($so == "-") {
            $sortsql.=" DESC";
        }
        if (!Opts::getOpt("window_ids")) {
            $wids=Tw::twToIds();
        } else {
            $wids=Opts::getOpt("window_ids");
        }
        if (Opts::getOpt("ic_notsame")) {
            $notsamesql="AND ((windowid1<>windowid2 AND ic.itemid1=ic.itemid2) OR (windowid1=windowid2 AND ic.itemid1<>ic.itemid2))";
        } else {
            $notsamesql="";
        }
        if (count($wids)>0) {
            $windowidsql=sprintf("is1.windowid IN (%s) AND is2.windowid IN (%s) AND",join(",",$wids),join(",",$wids));
        } else {
            throw new Exception("No windows matched query for item correlation.");
        }
        $rows=self::mquery(
                "SELECT windowid1,windowid2,itemid1,itemid2,corr,ic.cnt,ic.loi AS icloi
                 FROM itemcorr ic
                 JOIN itemstat is1 ON (ic.itemid1=is1.itemid AND ic.windowid1=is1.windowid)
                 JOIN itemstat is2 ON (ic.itemid2=is2.itemid AND ic.windowid2=is2.windowid)
                 JOIN timewindow tw ON (ic.windowid1=tw.id)
                 WHERE 
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                         true
                     AND ic.loi>?
                     $corrsql
                     AND ic.corr>? AND ic.corr<? 
                     $notsamesql
                 ORDER BY $sortsql
                 LIMIT ?
                ",Opts::getOpt("ic_minloi"),Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("max_rows"));
        return($rows);
    }
    
    /**
    * Search items and returns itemids
    * @param $withwindows If true, return windowids and itemids. If false, only itemids are returned
    * @param $computed If true, return computed correlations. If false, return items to compute correlations
    * @return Array()
    * @throws Exception
    */
    static function icToIds($withwindows=false,$computed=false) {
        if (!$computed) {
            $ids=self::icSearchItemsToIc(); 
        } else {
            $ids=self::icSearch();
        }
        $rows=$ids->fetchAll();
        if (count($rows)==0) {
            throw new Exception("No items for correlations (icToIds,computed=$computed).");
        }
        $icids=Array();
        foreach ($rows as $row) {
            if ($withwindows) {
                if (isset($row->icitemid1)) {
                    $icitemid1=$row->icitemid1;
                } else {
                    $icitemid1=null;
                }
                if (isset($row->icitemid2)) {
                    $icitemid2=$row->icitemid2;
                } else {
                    $icitemid2=null;
                }
                $icids[]=Array(
                    "itemid1" => $row->itemid1,
                    "itemid2" => $row->itemid2,
                    "windowid1" => $row->windowid1,
                    "windowid2" => $row->windowid2,
                    "icitemid1" => $icitemid1,
                    "icitemid2" => $icitemid2,
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
    
    /**
    * Return item correlation statistics. Item based
    * @return Nette\Database\Result
    * @throws Exception
    */
    static function icStats() {
        if (Opts::getOpt("itemids")) {
            $itemidssql=sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND",join(",",Opts::getOpt("itemids")),join(",",Opts::getOpt("itemids")));
        } else {
            $itemidssql="";
        }
        if (Opts::getOpt("hostids")) {
            $hostidssql=sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND",join(",",Opts::getOpt("hostids")),join(",",Opts::getOpt("hostids")));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds();
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
                ",Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("max_rows"));
        return($rows);
    }
    
    /**
    * Return item correlation statistics. Window based
    * @return Nette\Database\Result
    * @throws Exception
    */
    static function icTwStats() {
        if (Opts::getOpt("itemids")) {
            $itemidssql=sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND",join(",",Opts::getOpt("itemids")),join(",",Opts::getOpt("itemids")));
        } else {
            $itemidssql="";
        }
        if (Opts::getOpt("hostids")) {
            $hostidssql=sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND",join(",",Opts::getOpt("hostids")),join(",",Opts::getOpt("hostids")));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds();
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
                ",Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("max_rows"));
        return($rows);
    }
    
    /**
    * Compute item correlations
    */
    static function icMultiCompute() {
        $cids = self::icToIds(true,false);
        if (!$cids) {
            throw new Exception("No items for correlations (icMultiCompute).");
        }
        $wids = self::extractIds($cids, array("windowid1", "windowid2", "itemid1", "itemid2"));
        CliDebug::warn(sprintf("Need to look on correlations for %d combinations (%d items and %d windows, mode %s, seconds=%s)...",
                count($cids), count($wids["itemid1"])+count($wids["itemid2"]), count($wids["windowid1"]) + count($wids["windowid2"]), Opts::getOpt("corr_type"), join(",", Opts::getOpt("window_length"))));
        if (count($cids) == 0) {
            return(false);
        }
        $i = 0;
        foreach ($wids["windowid1"] as $wid1) {
            $i++;
            $w1 = Tw::twGet($wid1);
            $j = 0;
            foreach ($wids["windowid2"] as $wid2) {
                if (!self::IdsSearch(
                                Array(
                            "windowid1" => $wid1,
                            "windowid2" => $wid2,
                                ), $cids)) {
                    continue;
                }
                $j++;
                CliDebug::info(sprintf("Windows %d-%d:", $wid1, $wid2));
                $w2 = Tw::twGet($wid2);
                $icrows = self::zcquery("
                    SELECT  h1.itemid AS itemid1,
                            h2.itemid AS itemid2,
                            COUNT(*) AS cnt,
                            AVG(h1.value*h2.value)-AVG(h1.value)*AVG(h2.value) AS cov,
                            STDDEV(h1.value) AS stddev1, STDDEV(h2.value) AS stddev2
                    FROM history h1
                    JOIN history h2 ON (ABS(h1.clock-h2.clock+(?))<? AND h2.itemid IN (?))
                    WHERE h1.itemid IN (?)
                        AND h1.clock>? AND h1.clock<?
                        AND h2.clock>? AND h2.clock<?
                    GROUP BY h1.itemid,h2.itemid
                    HAVING (COUNT(*)>=? AND COUNT(*)<=?)
                    
                     UNION

                     SELECT  h1.itemid AS itemid1,
                            h2.itemid AS itemid2,
                            COUNT(*) AS cnt,
                            AVG(h1.value*h2.value)-AVG(h1.value)*AVG(h2.value) AS cov,
                            STDDEV(h1.value) AS stddev1, STDDEV(h2.value) AS stddev2
                    FROM history_uint h1
                    JOIN history_uint h2 ON (ABS(h1.clock-h2.clock+(?))<? AND h2.itemid IN (?))
                    WHERE h1.itemid IN (?)
                        AND h1.clock>? AND h1.clock<?
                        AND h2.clock>? AND h2.clock<?
                    GROUP BY h1.itemid, h2.itemid
                    HAVING (COUNT(*)>=? AND COUNT(*)<=?)
                    ", $w2["fstamp"] - $w1["fstamp"], Opts::getOpt("time_precision"), $wids["itemid1"], $wids["itemid2"], $w1["fstamp"], $w1["tstamp"], $w2["fstamp"], $w2["tstamp"], Opts::getOpt("min_values_for_corr"), Opts::getOpt("max_values_for_corr"), $w2["fstamp"] - $w1["fstamp"], Opts::getOpt("time_precision"), $wids["itemid1"], $wids["itemid2"], $w1["fstamp"], $w1["tstamp"], $w2["fstamp"], $w2["tstamp"], Opts::getOpt("min_values_for_corr"), Opts::getOpt("max_values_for_corr")
                );
                $mincorr = 0;
                $maxcorr = 0;
                self::mbegin();
                foreach ($icrows as $icrow) {
                    $icrow->windowid1 = $wid1;
                    $icrow->windowid2 = $wid2;
                    if ($icrow->stddev1 * $icrow->stddev2 >0) {
                        $icrow->corr=$icrow->cov/($icrow->stddev1 * $icrow->stddev2);
                    } else {
                        $icrow->corr=0;
                    }
                    if ($icrow->corr === null || $icrow->cnt < Opts::getOpt("min_values_for_corr")) {
                        $icrow->corr = 0;
                    }
                    if (abs($icrow->corr) > 1) {
                        if (abs($icrow->corr) > 1.1) {
                            CliDebug::err(sprintf("Bad correlation %dx%d=%f! Ignoring.\n", $icrow->itemid1, $icrow->itemid2, $icrow->corr));
                        } else {
                            $icrow->corr=round($icrow->corr);
                        }
                        continue;
                    }
                    $mincorr = min($mincorr, abs($icrow->corr));
                    $maxcorr = max($maxcorr, abs($icrow->corr));
                    $wrow = Array(
                        "windowid1" => $icrow->windowid1,
                        "windowid2" => $icrow->windowid2,
                        "itemid1" => $icrow->itemid1,
                        "itemid2" => $icrow->itemid2,
                        "corr" => $icrow->corr,
                        "cnt" => $icrow->cnt
                    );
                    self::mquery("DELETE FROM itemcorr WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?", $icrow->windowid1, $icrow->windowid2, $icrow->itemid1, $icrow->itemid2);
                    $iic = self::mquery("INSERT INTO itemcorr", $wrow);
                }
                CliDebug::info(sprintf("<%f,%f>,", $mincorr, $maxcorr));
                self::mcommit();
            }
        }
        CliDebug::warn("Done\n");
    }

    /**
    * Delete item correlations
    */
    static function icDelete() {
        $windows=Tw::twToIds();
        CliDebug::warn(sprintf("Will delete item correlations from %d windows.\n",count($windows)));
        if (count($windows)==0) {
            return(false);
        }
        self::mbegin();
        self::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$windows,$windows);
        self::mcommit();
    }
    
    /**
    * Update item correlations LOI. 
    */
    static function icLoi() {
        $twids=  Tw::twToIds();
        if (count($twids)==0) {
            throw new Exception("No item correlations for loi.");
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
