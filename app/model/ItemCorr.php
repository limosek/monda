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
            $loisql="AND (is1.loi>0 AND is2.loi>0)";
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
            default:
                $this->dbg->warn("Bad correlation specification $opts->corr. Using samewindow \n");
                $join1sql="is1.windowid=is2.windowid";
                $sameiwsql="AND is1.itemid<>is2.itemid";
                break;
        }
        $rows=$this->mquery(
                "SELECT
                        is1.itemid AS itemid1,is2.itemid AS itemid2,
                        is1.windowid AS windowid1, is2.windowid AS windowid2,
                        ic.itemid1 As icitemid1,ic.itemid2 AS icitemid2,
                        ic.windowid1 AS icwindowid1,ic.windowid2 AS icwindowid2,
                        tw1.tfrom AS tfrom1, tw1.seconds AS seconds1,
                        tw2.tfrom AS tfrom2, tw2.seconds AS seconds2
                 FROM itemstat is1
                 JOIN itemstat is2 ON ($join1sql)
                 LEFT JOIN itemcorr ic ON (is1.itemid=itemid1 AND itemid2=is2.itemid AND is1.windowid=windowid1 AND is2.windowid=windowid2)
                 JOIN timewindow tw1 ON (is1.windowid=tw1.id)
                 JOIN timewindow tw2 ON (is2.windowid=tw2.id)
                 WHERE $itemidssql $hostidssql $windowidsql true
                    AND tw1.seconds=tw2.seconds
                    AND (is1.windowid<=is2.windowid)
                    AND (is1.itemid<=is2.itemid)
                    $emptysql $sameiwsql $loisql
                 ORDER BY (is1.loi+is2.loi) DESC, is2.loi DESC, ic.windowid1,ic.windowid2,ic.itemid1,ic.itemid2
                 LIMIT ?",$opts->maxicrows
                );
        return($rows);
    }
    
    function icToIds($opts,$pkey=false) {
        $ids=$this->icSearch($opts);
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
        $is=New ItemStat($opts);
        $itemids=$is->isToIds($opts);
        $cids=$this->icToIds($opts,true);
        if (count($cids)==0) {
            return(false);
        }
        $i=0;
        foreach ($cids as $cid) {
            $i++;
            $this->dbg->warn(sprintf("Computing correlations for w1=%s,i1=%s<=>w2=%s,i2=%s (%d of %d)\n",$cid["windowid1"],$cid["itemid1"],$cid["windowid2"],$cid["itemid2"],$i,count($cids)));
            $this->mbegin();
            $icrows=$this->zcquery("
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
                ",
                    $cid["tto2"]-$cid["tto1"],$opts->timeprecision,
                        $cid["itemid1"],$cid["itemid2"],$cid["tfrom1"],$cid["tto1"],$cid["tfrom2"],$cid["tto2"],
                    $cid["tto2"]-$cid["tto1"],$opts->timeprecision,
                        $cid["itemid1"],$cid["itemid2"],$cid["tfrom1"],$cid["tto1"],$cid["tfrom2"],$cid["tto2"]
                    );
            if (count($icrows)==0) {
                $dic=$this->mquery("
                    DELETE FROM itemcorr
                    WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?",
                        $cid["windowid1"],$cid["windowid2"],$cid["itemid1"],$cid["itemid2"]);
                $iic=$this->mquery("INSERT INTO itemcorr ",Array(
                    "windowid1" => $cid["windowid1"],
                    "windowid2" => $cid["windowid2"],
                    "itemid1" => $cid["itemid1"],
                    "itemid2" => $cid["itemid2"],
                    "corr" => 0
                ));
            }
            foreach ($icrows as $icrow) {
                $dic=$this->mquery("
                    DELETE FROM itemcorr
                    WHERE windowid1=? AND windowid2=? AND itemid1=? AND itemid2=?",
                        $cid["windowid1"],$cid["windowid2"],$icrow->itemid1,$icrow->itemid2);
                $icrow->windowid1=$cid["windowid1"];
                $icrow->windowid2=$cid["windowid2"];
                if ($icrow->corr===null) {
                    $icrow->corr=0;
                }
                $iic=$this->mquery("INSERT INTO itemcorr ",$icrow);
            }
            $this->mcommit();
        }
    }
    
    function icLoi($opts) {
        
    }
}

?>
