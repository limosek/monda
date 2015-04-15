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
        
        $windows=Tw::twGet($wids,true);
        $widstxt=join(",",$wids);
        $casesql="CASE \n";
        foreach ($windows as $w) {
            $casesql.=sprintf("WHEN clock BETWEEN %d AND %d THEN %d\n",$w->fstamp,$w->tstamp,$w->id);
        }
        $casesql.="END\n";
        $wstats=Tw::twStats($opts);
        CliDebug::warn("Computing item statistics for windows $widstxt (zabbix_id:$opts->zid,<$wstats->minfstamp-$wstats->maxtstamp>)\n");
        $items=self::isToIds($this->opts);
        if (count($items)>0) {
            $itemidsql=sprintf("AND itemid IN (%s)",join($items));
        } else {
            $itemidsql="";
        }
        $rows=self::zcquery(
            "SELECT $casesql AS wid,
                itemid AS itemid,
                    min(value) AS min,
                    max(value) AS max,
                    avg(value) AS avg,
                    stddev(value) AS stddev,
                    max(value)-min(value) AS delta,
                    count(*) AS cnt
                FROM ".$this->opts->zabbix_history_table."
                WHERE clock>=? and clock<? $itemidsql
                GROUP BY itemid,wid

                UNION

                SELECT $casesql AS wid,
                    itemid AS itemid,
                    min(value) AS min,
                    max(value) AS max,
                    avg(value) AS avg,
                    stddev(value) AS stddev,
                    max(value)-min(value) AS delta,
                    count(*) AS cnt
                FROM ".$this->opts->zabbix_history_uint_table."
                WHERE clock>? and clock<? $itemidsql
                
                GROUP BY itemid,wid
                ORDER BY wid,itemid
                ",
                $wstats->minfstamp,$wstats->maxtstamp,$wstats->minfstamp,$wstats->maxtstamp);
        self::mbegin();
        if (count($rows)==0) {
            $d=self::mquery("DELETE FROM itemstat WHERE windowid IN (?)",$wids);
            self::mquery("UPDATE timewindow
                SET updated=?, found=0, processed=0, ignored=0, lowcnt=0, lowavg=0, stddev0=0 WHERE id IN (?)",
                New DateTime(),
                $wids);
            self::mcommit();
            return(false);
        }
        $hostids=Array();
        $itemids=Array();
        $rowscnt=0;
        $wid=false;
        foreach ($rows as $s) {
            if ($wid!=$s->wid) {
                if ($wid) {
                    Monda::mquery("UPDATE timewindow
                    SET updated=?, found=?, processed=?, ignored=?, lowcnt=?, lowavg=?, stddev0=? WHERE id=?",
                    New DateTime(),
                    Monda::sget("found"),
                    Monda::sget("processed"),
                    Monda::sget("ignored"),
                    Monda::sget("lowcnt"),
                    Monda::sget("lowavg"),
                    Monda::sget("stddev0"),
                    $wid);
                }
                Monda::sreset();
                $wid=$s->wid;
                $d=self::mquery("DELETE FROM itemstat WHERE windowid=?",$wid);
            }
            CliDebug::dbg(sprintf("Processing %d of %d items in window %d (id=%d,stddev=%.2f)\n",$rowscnt,count($rows),$wid,$s->itemid, $s->stddev));
            Monda::sadd("found");
            $rowscnt++;
            $itemids[]=$s->itemid;
            if ($s->stddev == 0) {
                Monda::sadd("ignored");
                Monda::sadd("stddev0");
                continue;
            }
            if ($s->cnt < $this->opts->min_values_per_window) {
                Monda::sadd("ignored");
                Monda::sadd("lowcnt");
                continue;
            }
            if ($s->avg < $this->opts->min_avg_for_cv) {
                Monda::sadd("ignored");
                Monda::sadd("lowavg");
                continue;
            }
            Monda::sadd("processed"); 
            $cv=$s->stddev/$s->avg;
            
            $r=self::mquery("INSERT INTO itemstat ",
                    Array(
                        "cnt" => $s->cnt,
                        "itemid" => $s->itemid,
                        "hostid" => null,
                        "windowid" => $wid,
                        "avg_" => $s->avg,
                        "min_" => $s->min,
                        "max_" => $s->max,
                        "stddev_" => $s->stddev,
                        "cv" => $cv
                    )); 
        }
        Monda::mquery("UPDATE timewindow
                    SET updated=?, found=?, processed=?, ignored=?, lowcnt=?, lowavg=?, stddev0=? WHERE id=?",
                    New DateTime(),
                    Monda::sget("found"),
                    Monda::sget("processed"),
                    Monda::sget("ignored"),
                    Monda::sget("lowcnt"),
                    Monda::sget("lowavg"),
                    Monda::sget("stddev0"),
                    $wid);
        self::mcommit();
        return(Monda::sget());
    }
    
    public function IsMultiCompute($opts) {
        if (\App\Presenters\BasePresenter::isOptDefault("empty")) {
            $opts->empty=true;
        }
        $wids=Tw::twToIds($this->opts);
        CliDebug::warn(sprintf("Need to compute itemstat for %d windows (from %s to %s).\n",count($wids),date("Y-m-d H:i",$opts->start),date("Y-m-d H:i",$opts->end)));
        self::isCompute($opts,$wids);
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
                ",$stat->maxcv,$wids);
        }
    }
}

?>
