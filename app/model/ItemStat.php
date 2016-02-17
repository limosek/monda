<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Utils\DateTime as DateTime,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class ItemStat extends Monda {
    
    function itemSearch($key = false, $host = false, $hostgroup = false) {
        $iq = Array(
            "monitored" => true
        );
        if ($host) {
            $iq["host"] = $host;
        }
        if ($hostgroup) {
            $iq["hostgroup"] = $hostgroup;
        }
        if ($key) {
            $iq["filter"] = Array("key_" => $key);
        }
        $i = Monda::apiCmd("itemGet",$iq);
        $ret = Array();
        foreach ($i as $item) {
            $ret[] = $item->itemid;
        }
        return($ret);
    }
    
    function itemInfo($itemid) {
        $iq = Array(
            "monitored" => true,
            "output" => "extend",
            "itemids" => Array($itemid)
        );
        $item = Monda::apiCmd("itemGet",$iq);
        return($item);
    }
    
    function searchToIds($opts) {
        $wids=Tw::searchToIds($opts);
        
    }
    
    function itemsToIds($opts) {
        if (is_array($opts->items)) {
            if (!is_array($opts->itemids)) {
                $opts->itemids=Array();
            }
            foreach ($opts->items as $item) {
                //List($host,$key)=preg_split("/:/",$item);
                $i=self::itemSearch($item,false,$opts->hostgroups);
                if (count($i)>0) {
                    $opts->itemids=array_merge($opts->itemids,$i);
                } else {
                    CliDebug::warn("Item $item not found! Continuing.\n");
                }
            }
        }
        return($opts);
    }
    
    function isSearch($opts) {
        if ($opts->itemids) {
            $itemidssql=sprintf("i.itemid IN (%s) AND",join(",",$opts->itemids));
        } else {
            $itemidssql="";
        }
        if ($opts->hostids) {
            $hostidssql=sprintf("i.hostid IN (%s) AND",join(",",$opts->hostids));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds($opts);
        if (count($wids)>0) {
            $windowidsql=sprintf("windowid IN (%s) AND",join(",",$wids));
        } else {
            return(false);
        }
        if ($opts->max_rows) {
            $limit="LIMIT ".$opts->max_rows;
        } else {
            $limit="";
        }
        $rows=self::mquery(
                "SELECT i.itemid AS itemid,
                        i.min_ AS min_,
                        i.max_ AS max_,
                        i.avg_ AS avg_,
                        i.stddev_ AS stddev_,
                        i.loi AS loi,
                        i.cnt AS cnt,
                        i.hostid AS hostid,
                        i.cv AS cv,
                        i.windowid AS windowid
                    FROM itemstat i
                    JOIN timewindow tw ON (i.windowid=tw.id)
                 WHERE i.loi>$opts->minloi AND i.loi IS NOT NULL AND $itemidssql $hostidssql $windowidsql true
                ORDER by i.loi DESC "
                . "$limit"
                );
        return($rows);
    }
    
    function isStats($opts) {
        $itemids=self::isToIds($opts);
        $rows=self::mquery("SELECT 
                i.itemid AS itemid,
                        MIN(i.min_) AS min_,
                        MAX(i.max_) AS max_,
                        AVG(i.avg_) AS avg_,
                        AVG(i.stddev_) AS stddev_,
                        AVG(i.loi)::integer AS loi,
                        AVG(i.cnt)::integer AS cnt,
                        AVG(i.cv) AS cv
                    FROM itemstat i
                 WHERE i.itemid IN (?)
                 AND i.loi IS NOT NULL
                 GROUP BY i.itemid
                 ORDER BY AVG(i.loi) DESC
                ",$itemids);
        return($rows);
    }
    
    function isToIds($opts,$pkey=false) {
        $ids=self::isSearch($opts);
        if (!$ids) {
            return(false);
        }
        $rows=$ids->fetchAll();
        $itemids=Array();
        $tmparr=Array();
        foreach ($rows as $row) {
            if ($pkey) {
                $itemids[]=Array(
                    "itemid" => $row->itemid,
                    "windowid" => $row->windowid,
                    "hostid" => $row->hostid);
                } else {
                    if (!array_key_exists($row->itemid,$tmparr)) {
                        $itemids[]=$row->itemid;
                        $tmparr[$row->itemid]=true;
                    }
                }
        }
        return($itemids);
    }
        
    function isCompute($opts,$wids) {
        
        if (!$wids) return;
        $windows=Tw::twGet($wids,true);
        $widstxt=join(",",$wids);
        $ttable="mwtmp_".rand(1000,9999);
        $crsql="CREATE TEMPORARY TABLE $ttable (s integer, e integer, id integer);";
        Monda::zquery($crsql);
        foreach ($windows as $w) {
            $crsqli="INSERT INTO $ttable VALUES ($w->fstamp,$w->tstamp,$w->id);\n";
            Monda::zquery($crsqli);
            $crsql.=$crsqli;
        }
        $wstats=Tw::twStats($opts);
        CliDebug::warn("Computing item statistics (zabbix_id:$opts->zid,<$wstats->minfstamp-$wstats->maxtstamp,max ".($wstats->maxtstamp-$wstats->minfstamp)." seconds>),".count($wids)." windows\n");
        $items=self::isToIds($this->opts);
        if (count($items)>0) {
            $itemidsql=sprintf("AND itemid IN (%s)",join($items));
        } else {
            $itemidsql="";
        }
        $rows=self::zquery(
            "SELECT w.id AS windowid,
                 itemid AS itemid,
                    min(value) AS min_,
                    max(value) AS max_,
                    avg(value) AS avg_,
                    stddev(value) AS stddev_,
                    count(*) AS cnt
                 FROM
                 (
                  SELECT itemid,clock,value FROM ".$opts->zabbix_history_table."
                  WHERE clock BETWEEN ? AND ?
                  UNION ALL
                  SELECT itemid,clock,value FROM ".$opts->zabbix_history_uint_table."
                  WHERE clock BETWEEN ? AND ?
                 ) AS h
                 JOIN $ttable w ON (clock BETWEEN w.s AND w.e)
                  $itemidsql
                 GROUP BY windowid,itemid
                 ORDER BY windowid,itemid",
                $wstats->minfstamp,$wstats->maxtstamp,$wstats->minfstamp,$wstats->maxtstamp);
        self::mbegin();
        self::mquery("DELETE FROM itemstat WHERE windowid IN (?)",$wids);
        Monda::sreset();
        $wid=false;
        while ($row=$rows->fetch()) {
            Monda::sadd("found");
            if ($row->stddev_<=$opts->min_stddev) {
                Monda::sadd("ignored");
                Monda::sadd("lowstddev");
                continue;
            }
            if ($row->cnt<$opts->min_values_per_window) {
                Monda::sadd("ignored");
                Monda::sadd("lowcnt");
                continue;
            }
            if ($row->avg_>$opts->min_avg_for_cv) {
                $cv=$row->stddev_/$row->avg_;
                if ($cv<=$opts->min_cv) {
                    Monda::sadd("ignored");
                    Monda::sadd("lowcv");
                    continue;
                }
            } else {
                Monda::sadd("ignored");
                Monda::sadd("lowavg");
                continue;
            }
            Monda::sadd("processed");
            Monda::mquery("INSERT INTO itemstat "
                    . "       (windowid,    itemid, min_,   max_,   avg_,   stddev_,    cnt,    cv) "
                    . "VALUES (?       ,    ?,      ?,      ?,      ?,      ?,          ?,      ?)",
                        $row->windowid,
                        $row->itemid,
                        $row->min_,
                        $row->max_,
                        $row->avg_,
                        $row->stddev_,
                        $row->cnt,
                        $cv
                    );
            if ($wid!=$row->windowid) {
                if ($wid) {
                    Monda::mquery("UPDATE timewindow
                    SET updated=?, found=?, processed=?, ignored=?, lowcnt=?, lowavg=?, lowstddev=?, lowcv=?
                    WHERE id=?",
                    New DateTime(),
                    Monda::sget("found"),
                    Monda::sget("processed"),
                    Monda::sget("ignored"),
                    Monda::sget("lowcnt"),
                    Monda::sget("lowavg"),
                    Monda::sget("lowstddev"),
                    Monda::sget("lowcv"),
                    $wid);
                }
                Monda::sreset();
                $wid=$row->windowid;
            }
        }
        if (Monda::sget("found")>0) {
            Monda::mquery("UPDATE timewindow
                    SET updated=?, found=?, processed=?, ignored=?, lowcnt=?, lowavg=?, lowstddev=?, lowcv=?
                    WHERE id=?",
                    New DateTime(),
                    Monda::sget("found"),
                    Monda::sget("processed"),
                    Monda::sget("ignored"),
                    Monda::sget("lowcnt"),
                    Monda::sget("lowavg"),
                    Monda::sget("lowstddev"),
                    Monda::sget("lowcv"),
                    $wid);
            
            $ret=Monda::sget();
        } else {
            $ret=false;
        }
        Monda::mcommit();
        Monda::zquery("DROP TABLE $ttable");
        return($ret);
    }
    
    public function IsZabbixHistory($opts) {
        $itemids=self::IsToIDs($opts);
        $windowids=  Tw::twToIds($opts);
        $timesql="";
        foreach ($windowids as $wid) {
            $w=  Tw::twGet($wid);
            $timesql.="OR (clock BETWEEN $w->fstamp AND $w->tstamp) ";
        }
        $hist=  Monda::zcquery("                
              SELECT itemid,clock,value FROM history WHERE (false $timesql) AND itemid IN (?)
              UNION ALL 
              SELECT itemid,clock,value FROM history_uint WHERE (false $timesql) AND itemid IN (?)
              ORDER BY clock,itemid
                ",$itemids,$itemids);
        $ret=Array();
        foreach ($hist as $h) {
            $ret[$h->itemid][$h->clock]=$h->value;
        }
        return($ret);
    }
    
    public function IsMultiCompute($opts) {
        if (\App\Presenters\BasePresenter::isOptDefault("empty")) {
            $opts->empty=true;
        }
        $wids=Tw::twToIds($this->opts);
        if ($opts->max_windows_per_query && count($wids)>$opts->max_windows_per_query) {
            foreach (array_chunk($wids,$opts->max_windows_per_query) as $subwids) {
                self::isCompute($opts,$subwids);
            }
            CliDebug::warn(sprintf("Need to compute itemstat for %d windows (from %s to %s).\n",count($wids),date("Y-m-d H:i",$opts->start),date("Y-m-d H:i",$opts->end)));
            
        } else {
            CliDebug::warn(sprintf("Need to compute itemstat for %d windows (from %s to %s).\n",count($wids),date("Y-m-d H:i",$opts->start),date("Y-m-d H:i",$opts->end)));
            self::isCompute($opts,$wids);
        }
    }
    
    public function IsDelete($opts) {
        $items=self::IsToIds($opts);
        $windowids=Tw::TwtoIds($opts);
        CliDebug::warn(sprintf("Will delete %d itemstat entries (%d windows).\n",count($items),count($windowids)));
        if (count($items)>0 && count($windowids)>0) {
            self::mbegin();
            self::mquery("DELETE FROM itemstat WHERE ?", $items);
            self::mquery("UPDATE timewindow SET updated=NULL,loi=0 WHERE id IN (?)",$windowids);
            self::mcommit();
        }
    }
    
    public function IsShow($opts) {
        $stats=self::mquery("
            SELECT 
                MIN(value),MAX(value),
            FROM itemstat
            JOIN timewindow ON (id=windowid)
            WHERE timewindow.serverid=?
            GROUP BY itemid
            ",$opts->zid);
        return($stats);
    }
    
    public function IsLoi($opts) {
        $wids=Tw::twToIds($opts);
        CliDebug::warn(sprintf("Need to compute itemstat loi for %d windows.\n",count($wids)));
        if (count($wids)>0) {
            $stat=self::mquery("
                SELECT MIN(cv) AS mincv,
                    MAX(cv) AS maxcv,
                    MIN(cnt) AS mincnt,
                    MAX(cnt) AS maxcnt
                FROM itemstat
                WHERE windowid IN (?)",$wids)->fetch();

            $lsql=self::mquery("
                UPDATE itemstat 
                SET loi=100*(cv/?)
                WHERE windowid IN (?)
                ",$opts->max_cv,$wids);
        }
    }
}

?>
