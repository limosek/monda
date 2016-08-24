<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    App\Model\Opts,
    App\Model\ItemStat,
    App\Model\Tw,
    Nette\Security\Passwords,
    Tracy\Debugger,
    Nette\Caching\Cache,
    Nette\Database\Context,
    \Exception;

/**
 * ItemCorr global class
 * It computes ans searches correlations between items
 */
class ItemCorr extends Monda {
    
    static public $similar=Array();

    /**
    * Search items which were preprocessed to compute item correlations
    * @return Nette\Database\Result
    * @throws Exception
    */
   static function icSearchItemsToIc() {
        $itemids = ItemStat::isToIds();
        if (count($itemids) > 0) {
            $itemidssql = sprintf("is1.itemid IN (%s) AND is2.itemid IN (%s) AND", join(",", $itemids), join(",", $itemids));
        } else {
            $itemidssql = "";
        }
        if (is_array(Opts::getOpt("hostids"))) {
            $hostidssql = sprintf("is1.hostid IN (%s) AND is2.hostid IN (%s) AND", join(",", Opts::getOpt("hostids")), join(",", Opts::getOpt("hostids")));
        } else {
            $hostidssql = "";
        }
        $wids = Tw::twToIds();
        if (count($wids) == 0) {
            throw New Exception("No windows matched query.");
        }

        if (preg_match("#/#", Opts::getOpt("ic_sort"))) {
            List($sc, $so) = preg_split("#/#", Opts::getOpt("ic_sort"));
        } else {
            $sc = Opts::getOpt("ic_sort");
            $so = "+";
        }
        if (Opts::getOpt("ic_all")) {
            $full = "true";
        } else {
            $full = "false";
        }
        switch ($sc) {
            case "start":
                $sortsql = "tw1.tfrom,is1.itemid,tw2.tfrom,is2.itemid";
                break;
            case "loi":
                $sortsql = "(tw1.tfrom,tw2.tfrom,tw1.loi+tw2.loi+is1.loi+is2.loi)";
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
                $join1sql = "is1.windowid=is2.windowid AND is1.itemid<>is2.itemid";
                $sameiwsql = "AND is1.itemid<>is2.itemid
                            AND is1.windowid=is2.windowid";
                break;
            case "samehour":
                $join1sql = "is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql = "AND is1.itemid=is2.itemid
                            AND tw1.seconds=" . Monda::_1HOUR . "
                            AND is1.windowid<>is2.windowid
                            AND extract(hour from tw1.tfrom)=extract(hour from tw2.tfrom)";
                break;
            case "samedow":
                $join1sql = "is1.itemid=is2.itemid AND is1.windowid<>is2.windowid";
                $sameiwsql = "AND is1.itemid=is2.itemid
                            AND tw1.seconds=" . Monda::_1DAY . "
                            AND is1.windowid<>is2.windowid
                            AND extract(dow from tw1.tfrom)=extract(dow from tw2.tfrom)";
                break;
        }
        if (Opts::getOpt("ic_notsamehost")) {
            $hostsql = "AND is1.hostid <> is2.hostid";
        } else {
            $hostsql = "";
        }
        $allrows = Array();
        foreach ($wids as $wid) {
            $rows = self::mquery(
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
                    $hostsql true
                    AND tw1.id=? AND tw2.id IN (?)
                    AND tw1.seconds=tw2.seconds
                    AND (is1.windowid<=is2.windowid)
                    AND (is1.itemid<=is2.itemid)
                    $sameiwsql
                    AND ((is1.loi>? AND is2.loi>?) OR $full)
                    AND ic.corr IS NULL
                 ORDER BY $sortsql
                 LIMIT ?", $wid, $wids, Opts::getOpt("is_minloi"), Opts::getOpt("is_minloi"), Opts::getOpt("ic_max_rows")
            );
            if ($rows->getRowCount() == Opts::getOpt("ic_max_rows")) {
                //CliDebug::warn(sprintf("Limiting output of possible correlations to %d of %d total combinations! Use ic_max_rows parameter to increase!\n", Opts::getOpt("ic_max_rows"), count($itemids) * count($itemids)));
            }
            foreach ($rows as $row) {
                $allrows[] = $row;
            }
        }
        return($allrows);
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
                $corrsql="AND ic.windowid1<>ic.windowid2 AND tw.seconds=".Monda::_1HOUR;
                break;
            case "samedow":
                $corrsql="AND ic.windowid1<>ic.windowid2 AND tw.seconds=".Monda::_1DAY;
                break;      
            case "all":
                $corrsql="AND true";
                break;      
            case "crosswindow":
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
        if (Opts::getOpt("ic_notsamehost")) {
            $notsamehostsql="AND is1.hostid <> is2.hostid";
        } else {
            $notsamehostsql="";
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
                     $notsamehostsql
                 ORDER BY $sortsql
                 LIMIT ?
                ",Opts::getOpt("ic_minloi"),Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("ic_max_rows"));
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
        Monda::profileStart("icToIds");
        CliDebug::dbg("icToIds start\n");
        if (!$computed) {
            $rows=self::icSearchItemsToIc(); 
        } else {
            $ids=self::icSearch();
            if ($ids) {
                $rows=$ids->fetchAll();
            }
        }
        
        if (count($rows)==0) {
            return(false);
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
                    "icitemid2" => $icitemid2
                      );
                } else {
                    $icids[$row->itemid1]=$row->itemid1;
                    $icids[$row->itemid2]=$row->itemid2;
                }
        }
        Monda::profileEnd("icToIds");
        CliDebug::dbg("icToIds result: ".count($icids)."\n");
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
                ",Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("ic_max_rows"));
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
        switch (Opts::getOpt("corr_type")) {
            case "samewindow":
                $corrsql="windowid1=windowid2 AND itemid1<>itemid2";
                break;
            case "samehour":
                $corrsql="windowid1<>windowid2 AND itemid1=itemid2 AND tw.seconds=".Monda::_1HOUR;
                break;
            case "samedow":
                $corrsql="ic.windowid1<>ic.windowid2 AND itemid1=itemid2 AND tw.seconds=".Monda::_1DAY;
                break;              
        }
        $rows=self::mquery(
                "SELECT
                        windowid1,windowid2,
                        COUNT(DISTINCT itemid1) AS icnt1,COUNT(DISTINCT itemid2) AS icnt2,
                        AVG(ic.corr)*COUNT(DISTINCT itemid1)*COUNT(DISTINCT itemid2) AS icorr,
                        AVG(corr) AS acorr,
                        AVG(ic.cnt) AS acnt,
                        AVG(ic.loi) AS aloi
                 FROM itemcorr ic
                 JOIN itemstat is1 ON (ic.itemid1=is1.itemid AND ic.windowid1=is1.windowid)
                 JOIN itemstat is2 ON (ic.itemid2=is2.itemid AND ic.windowid2=is2.windowid)
                 JOIN timewindow tw ON (ic.windowid1=tw.id)
                 WHERE 
                    (
                     $corrsql
                    )
                    AND
                    ((ic.corr>? AND ic.corr<?) OR ic.corr IS NULL)
                    AND
                    $itemidssql
                    $hostidssql
                    $windowidsql 
                    true
                 GROUP BY windowid1,windowid2
                 ORDER BY AVG(ic.corr) DESC
                 LIMIT ?
                ",Opts::getOpt("min_corr"),Opts::getOpt("max_corr"),Opts::getOpt("ic_max_rows"));
        return($rows);
    }
    
    /**
    * Compute item correlations
    */
   static function icMultiCompute() {
        $cids = self::icToIds(true, false);
        if (!$cids) {
            return(false);
        }
        $wids = self::extractIds($cids, array("windowid1", "windowid2", "itemid1", "itemid2"));
        $itemids = Array_unique(Array_merge($wids["itemid1"], $wids["itemid2"]));
        $windowids = Array_unique(Array_merge($wids["windowid1"], $wids["windowid2"]));
        CliDebug::warn(sprintf("Need to look on correlations for %d combinations (%d items and %d windows, mode %s, seconds=%s)\n", count($cids), count($itemids), count($windowids), Opts::getOpt("corr_type"), join(",", Opts::getOpt("window_length"))));
        if (count($cids) == 0) {
            return(false);
        }
        $i = 0;
        foreach ($windowids as $wid1) {
            $i++;
            $w1 = Tw::twGet($wid1);
            $j = 0;
            foreach ($windowids as $wid2) {
                CliDebug::info("w");
                if ($wid1>$wid2) continue;
                if (Opts::getOpt("corr_type")=="samewindow") {
                    if ($wid1<>$wid2) continue;
                }
                $j++;
                $w2 = Tw::twGet($wid2);
                $itemscount=0;
                foreach (array_chunk($itemids, Opts::getOpt("ic_max_items_at_once")) as $itemids_part) {
                    $itemscount+=count($itemids_part);
                    CliDebug::info(sprintf("Windows %d-%d (%.1f%%), total %.2f%%.\n", $wid1, $wid2,100*$itemscount/count($itemids),100*$i/count($windowids)));
                    CliDebug::dbg(sprintf("Items: %s\n", join(",",$itemids_part)));
                    self::IcCompute(
                            $wid1,$wid2,
                            $itemids_part,
                            $w1["fstamp"],$w1["tstamp"],
                            $w2["fstamp"],$w2["tstamp"],
                            Opts::getOpt("time_precision"),     
                            Opts::getOpt("min_values_for_corr"),
                            Opts::getOpt("max_values_for_corr"),
                            Opts::getOpt("ic_all")
                            );
                }
            }
        }
        self::icLoi();
        CliDebug::warn("Done\n");
    }
    /*
     * Add itemcorr row if not exists in db
     * If needed, add itemstat too with empty values to fullfill sql relations
     * Sort itemid and windowid so each correlation will be inserted only one time
     * windowid1
     */
    public function IcAddIfEmpty($wid1, $wid2, $itemid1, $itemid2, $corr, $cnt) {
        if ($wid1 > $wid2) {
            $w1 = $wid2;
            $w2 = $wid1;
            $i1 = $itemid2;
            $i2 = $itemid1;
        } elseif ($wid1==$wid2) {
            $w1 = $wid1;
            $w2 = $wid2;
            if ($itemid1 > $itemid2) {
                $i1 = $itemid2;
                $i2 = $itemid1;
            } else {
                $i1 = $itemid1;
                $i2 = $itemid2;
            }
        } else {
            $i1 = $itemid1;
            $i2 = $itemid2;
        }
        $pq = self::mquery("SELECT * FROM itemcorr WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?", $w1, $w2, $i1, $i2);
        if ($pq->getRowCount() == 0) {
            ItemStat::IsAddIfEmpty($w1, $i1);
            ItemStat::IsAddIfEmpty($w2, $i2);
            self::mquery("INSERT INTO itemcorr", Array(
                "windowid1" => $w1,
                "windowid2" => $w2,
                "itemid1" => $i1,
                "itemid2" => $i2,
                "corr" => $corr,
                "cnt" => $cnt
            ));
            return(true);
        } else {
            CliDebug::dbg("Correlation $w1-$w2-$i1-$i2 already in DB.\n");
            return(false);
        }
    }

    public function IcCompute($wid1, $wid2, $itemids, $w1_start, $w1_end, $w2_start, $w2_end, $tp, $minv, $maxv, $all=false, $noinsert = false) {
        Monda::profileStart("IcCompute");
        $ckey = "IcCompute " . serialize(func_get_args());
        $rows = self::$cache->load($ckey);
        if ($rows === NULL) {
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
                    ", $w2_start - $w1_start, $tp, $itemids, $itemids, $w1_start, $w1_end, $w2_start, $w2_end, $minv, $maxv, $w2_start - $w1_start, $tp, $itemids, $itemids, $w1_start, $w1_end, $w2_start, $w2_end, $minv, $maxv
            );
            $mincorr = 0;
            $maxcorr = 0;
            $rows_added = 0;
            $rows_found = 0;
            $rows = Array();
            if (!$noinsert) {
                self::mbegin();
            }
            if ($all && Opts::getOpt("corr_type") == "samewindow") {
                foreach ($itemids as $itemid1) {
                    foreach ($itemids as $itemid2) {
                        if ($itemid1>$itemid2) continue;
                        $wrow = Array(
                            "windowid1" => $wid1,
                            "windowid2" => $wid2,
                            "itemid1" => $itemid1,
                            "itemid2" => $itemid2,
                            "corr" => (int) ($itemid1 == $itemid2),
                            "cnt" => 0
                        );
                        $rows[]=$wrow;
                        CliDebug::info("_");
                        if (!$noinsert) {
                            try {
                                if (self::IcAddIfEmpty($wid1,$wid2,$itemid1,$itemid2,(int) ($itemid1 == $itemid2),0)) {
                                     $rows_added++;
                                } else {
                                    $rows_found++;
                                }
                            } catch (Nette\Database\UniqueConstraintViolationException $e) { }
                        }
                    }
                }
            }
            foreach ($icrows as $icrow) {
                CliDebug::info(".");
                if ($icrow->stddev1 * $icrow->stddev2 > 0) {
                    $icrow->corr = $icrow->cov / ($icrow->stddev1 * $icrow->stddev2);
                } else {
                    $icrow->corr = 0;
                }
                if ($icrow->corr === null || $icrow->cnt < Opts::getOpt("min_values_for_corr")) {
                    $icrow->corr = 0;
                }
                $mincorr = min($mincorr, abs($icrow->corr));
                if ($itemid1 <> $itemid2) {
                    $maxcorr = max($maxcorr, abs($icrow->corr));
                }
                $wrow = Array(
                    "windowid1" => $wid1,
                    "windowid2" => $wid2,
                    "itemid1" => $itemid1,
                    "itemid2" => $itemid2,
                    "corr" => $icrow->corr,
                    "cnt" => $icrow->cnt
                );
                $rows[] = $wrow;
                if (!$noinsert) {
                    if (self::IcAddIfEmpty($wid1,$wid2,$icrow->itemid1,$icrow->itemid2,
                            $icrow->corr,$icrow->cnt)) {
                         $rows_added++;
                    } else {
                        $rows_found++;
                    }
                }
            }
            CliDebug::info(sprintf("Rows: $rows_added added, $rows_found already in DB, corr range:<%f,%f>\n", $mincorr, $maxcorr));
            if (!$noinsert) {
                self::mcommit();
            }
            self::$cache->save($ckey, $rows, array(
                Cache::EXPIRE => Opts::getOpt("ic_cache_expire"),
            ));
        }
        Monda::profileEnd("IcCompute");
        return($rows);
    }

    public function TwCorrelationsByItemid($tw, $itemids) {
        $ret = Array();
        $itemids=  array_unique($itemids);
        foreach ($itemids as $itemid1) {
            foreach ($itemids as $itemid2) {
                if ($itemid1>=$itemid2) {
                    continue;
                }
                $ret["windowid"] = $tw;
                if ($itemid1 == $itemid2) {
                    if (Opts::getOpt("ic_notsame")) {
                        continue;
                    } else {
                        $ret[$itemid1 . "-" . $itemid2] = 1;
                    }
                } elseif ($itemid1 < $itemid2) {
                    $ret[$itemid1 . "-" . $itemid2] = "NaN";
                } else {
                    continue;
                }
            }
        }
        Opts::setOpt("window_ids", Array($tw));
        Opts::setOpt("itemids", $itemids);
        Opts::setOpt("hostids", false);
        $ritemids=array_flip($itemids);
        $items = ItemCorr::icSearch()->fetchAll();
        foreach ($items as $item) {
            if ($item->itemid1 < $item->itemid2 && array_key_exists($item->itemid1,$ritemids) && array_key_exists($item->itemid2,$ritemids)) {
                $ret[$item->itemid1 . "-" . $item->itemid2] = $item->corr;
            }
        }
        return($ret);
    }
    
    public function FindSimilarItems($itemids) {
        $wids=Tw::twToIds();
        $mq = Monda::mquery("SELECT itemid1,itemid2,corr
                FROM itemcorr
                WHERE (itemid1 IN (?) OR itemid2 IN (?))
                   AND corr>=? 
                   AND
                   (
                     (itemid1<>itemid2) AND (windowid1=windowid2)
                    )
                    AND windowid1 IN (?) OR windowid2 IN (?)
                ORDER BY  (windowid1=windowid2) DESC, loi DESC, corr DESC
                LIMIT ?", $itemids, $itemids, Opts::getOpt("similar_corr"), $wids, $wids, Opts::getOpt("similar_items")*3);
        if ($mq) {
            $data=$mq->fetchAll();
            $found=count($itemids);
            foreach ($data as $row) {
                $itemids[]=$row->itemid1;
                $itemids[]=$row->itemid2;
                $itemids=array_unique($itemids);
                self::$similar[$row->itemid1][$row->itemid2]=$row->corr;
                self::$similar[$row->itemid2][$row->itemid1]=$row->corr;
                if (count($itemids)-$found>=Opts::getOpt("similar_items")) break;
            }
        }
        CliDebug::warn(sprintf("Found %d similar items.\n",count($itemids)-$found));
        return($itemids);
    }

    /**
     * Delete item correlations
     */
    static function icDelete() {
        Opts::setOpt("tw_max_rows", false);
        $windows = Tw::twToIds();
        CliDebug::warn(sprintf("Will delete item correlations from %d windows.\n", count($windows)));
        if (count($windows) == 0) {
            return(false);
        }
        self::mbegin();
        self::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)", $windows, $windows);
        self::mcommit();
    }

    /**
     * Update item correlations LOI. 
     */
    static function icLoi($full=false) {
        Opts::setOpt("tw_max_rows", false);
        $twids = Tw::twToIds();
        if (count($twids) == 0) {
            throw new Exception("No item correlations for loi.");
        }
        if ($full) {
            $fullsql="";
        } else {
            $fullsql="AND loi=0";
        }
        foreach ($twids as $tw) {
        $lsql = Monda::mquery("
            UPDATE itemcorr ic
            SET loi=ABS(corr)
            * (SELECT COUNT(*) FROM itemcorr ic1 WHERE ic1.windowid1=? AND (ic.itemid1=ic1.itemid1))
            WHERE windowid1=? $fullsql
            ", $tw, $tw);
        }
    }

}

?>
