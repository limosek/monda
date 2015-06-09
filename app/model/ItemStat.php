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
    
    static function itemSearch($key = false, $host = false, $hostgroup = false) {
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
    
    static function itemInfo($itemid) {
        $iq = Array(
            "monitored" => true,
            "output" => "extend",
            "itemids" => Array($itemid)
        );
        $item = Monda::apiCmd("itemGet",$iq);
        return($item);
    }
    
    static function searchToIds($opts) {
        $wids=Tw::searchToIds($opts);
        
    }
    
    static function itemsToIds($opts) {
        if (is_array($opts->items)) {
            if (!is_array($opts->itemids)) {
                $opts->itemids=Array();
            }
            foreach ($opts->items as $item) {
                if (preg_match("/@(.*):\$/",$item,$regs)) {
                    $hostgroup=$regs[1];
                    $hostids = HostStat::HostGroupsToIds($hostgroup);
                    $opts->itemids = array_merge($opts->itemids, HostStat::hostids2itemids($hostids));
                } elseif (preg_match("/(.*):\$/",$item,$regs)) {
                    $host=$regs[1];
                    $opts->itemids = array_merge($opts->itemids, HostStat::hostids2itemids(
                                                                    HostStat::hosts2ids($host)));
                } else {
                    $i=self::itemSearch($item,false,$opts->hostgroups);
                    if (count($i)>0) {
                        $opts->itemids=array_merge($opts->itemids,$i);
                    } else {
                        CliDebug::warn("Item $item not found! Continuing.\n");
                    }
                }
            }
        }
        return($opts);
    }
    
    static function isSearch($opts) {
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
        if ($opts->isloionly) {
            $loisql="i.loi>0 AND";
        } else {
            $loisql="";
        }

        $wids=Tw::twToIds($opts);
        if (count($wids)>0) {
            $windowidsql=sprintf("windowid IN (%s) AND",join(",",$wids));
        } else {
            return(false);
        }
        if ($opts->max_items) {
            $limit="LIMIT ".$opts->max_items;
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
                 WHERE $itemidssql $hostidssql $windowidsql $loisql true
                ORDER by i.loi DESC "
                . "$limit"
                );
        return($rows);
    }
    
    static function isToIds($opts,$pkey=false) {
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
    
    static function isZabbixGetHistory($opts,$samples=false) {
        $opts=\App\Model\Monda::$opts;
        $w=Tw::twGet($opts->wids);
        $items=$opts->itemids;
        $ttable="mistmp_".rand(1000,9999);
        
        if (!$samples) $samples=500;
        self::monda2zabbix(ItemStat::isSearch($opts),$ttable);
        if ($opts->max_items) {
            $limit="LIMIT ".$opts->max_items;
        } else {
            $limit="";
        }
        $step=round(($w->tstamp-$w->fstamp)/$samples);
        $rows=self::zquery(
            "SELECT
                zh.itemid,((clock/$step)::bigint)*$step AS clock,
                    AVG(value),
                    CASE WHEN (tt.max_-tt.min_)<>0 THEN AVG(value)/(tt.max_-tt.min_) ELSE 0 END AS value,
                    COUNT(*) AS cnt
                  FROM ".$opts->zabbix_history_table." zh
                  JOIN $ttable tt ON (zh.itemid=tt.itemid)
                  WHERE clock BETWEEN ? AND ?
                  GROUP BY zh.itemid,((clock/$step)::bigint)*$step,tt.max_,tt.min_
                  UNION ALL
                  SELECT zhu.itemid,((clock/$step)::bigint)*$step AS clock,
                     AVG(value) AS value,
                     CASE WHEN (tt.max_-tt.min_)<>0 THEN AVG(value)/(tt.max_-tt.min_) ELSE 0 END AS value,
                     COUNT(*) AS cnt
                  FROM ".$opts->zabbix_history_uint_table." zhu
                  JOIN $ttable tt ON (zhu.itemid=tt.itemid)
                  WHERE clock BETWEEN ? AND ?
                  GROUP BY zhu.itemid,((clock/$step)::bigint)*$step,tt.max_,tt.min_
                  ORDER BY clock
                  $limit
                 ",$w->fstamp,$w->tstamp,$w->fstamp,$w->tstamp);
        $oitems=Array();

        $maxcnt=0;
        $mincnt=1000;
    
        while ($row=$rows->fetch()) {
            $fitems[$row->itemid]=true;
            $oitems[$row->clock]["time"]=date("Y/m/d H:i",$row->clock);
            $oitems[$row->clock][$row->itemid]=$row->value;
            $maxcnt=max($maxcnt,count($oitems[$row->clock]));
            $mincnt=min($mincnt,count($oitems[$row->clock]));
        }
        /*if ($maxcnt<>$mincnt) {
            foreach ($oitems as $k=>$oi) {
                if (count($oi)<>($maxcnt-1)) {
                    unset($oitems[$k]);
                }
            }
        }*/
        foreach ($oitems as $k=>$v) {
            reset($items);
            foreach ($items as $i) {
                if (!array_key_exists($i,$v)) {
                    $oitems[$k][$i]=0;
                }
            }
        }
        return($oitems);
    }
        
    static function isCompute($opts,$wids) {
        
        $windows=Tw::twGet($wids,true);
        $widstxt=join(",",$wids);
        $ttable="mwtmp_".rand(1000,9999);
        self::monda2zabbix(
                Tw::twSearch($opts),
                $ttable
             );
        $wstats=Tw::twStats($opts);
        CliDebug::warn("Computing item statistics (zabbix_id:$opts->zid,<$wstats->minfstamp-$wstats->maxtstamp,max ".($wstats->maxtstamp-$wstats->minfstamp)." seconds>),".count($wids)." windows\n");
        $opts=$this->opts;
        $opts->isloionly=false;
        $items=$opts->itemids;
        if ($items && count($items)>0) {
            $itemidsql=sprintf("AND itemid IN (%s)",join(",",$items));
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
                 JOIN $ttable w ON (clock BETWEEN w.fstamp AND w.tstamp)
                  $itemidsql
                 GROUP BY windowid,itemid
                 ORDER BY windowid,itemid",
                $wstats->minfstamp,$wstats->maxtstamp,$wstats->minfstamp,$wstats->maxtstamp);
        self::mbegin();
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
            Monda::mquery("DELETE FROM itemstat WHERE windowid=? AND itemid=?",$row->windowid,$row->itemid);
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
    
    static public function IsMultiCompute($opts) {
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
    
    static public function IsDelete($opts) {
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
    
    static function IsStats($opts) {
        $windowids=Tw::TwToIds($opts);
        $itemids=self::IsToIds($opts);
        $stats=self::mquery("
            SELECT 
                itemid, MIN(min_) AS min_, MAX(max_) AS max_, AVG(avg_) AS avg_,
                MIN(itemstat.loi) AS minloi, MAX(itemstat.loi) AS maxloi, AVG(itemstat.loi) AS avgloi, SUM(itemstat.loi) AS sumloi,
                MIN(cv) AS mincv, AVG(cv) AS avgcv, MAX(cv) AS maxcv,
                MIN(cnt) AS mincnt, AVG(cnt) AS avgcnt, MAX(cnt) AS maxcnt,
                MIN(itemstat.loi) AS minloi, AVG(itemstat.loi) AS avgloi, MAX(itemstat.loi) AS maxloi,
                COUNT(*) AS cnt
            FROM itemstat
            JOIN timewindow ON (id=windowid)
            WHERE timewindow.serverid=? AND windowid IN (?) AND itemid IN (?)
            GROUP BY itemid
            ORDER BY AVG(itemstat.loi) DESC
            ",$opts->zid,$windowids,$itemids);
        return($stats);
    }
    
    static function IsLoi($opts) {
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
                ",$stat->maxcv,$wids);
        }
    }
}

?>
