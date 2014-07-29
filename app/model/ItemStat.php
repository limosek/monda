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
        $i = $this->apiCmd("itemGet",$iq);
        $ret = Array();
        foreach ($i as $item) {
            $ret[] = $item->itemid;
        }
        return($ret);
    }
    
    function itemInfo($itemid) {
        $iq = Array(
            "monitored" => true,
            "selectHosts" => "refer",
            "itemids" => Array($itemid)
        );
        $item = $this->apiCmd("itemGet",$iq);
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
                $i=$this->itemSearch($item,false,$opts->hostgroups);
                if (count($i)>0) {
                    $opts->itemids=array_merge($opts->itemids,$i);
                } else {
                    $this->dbg->warn("Item $item not found! Continuing.\n");
                }
            }
        }
        return($opts);
    }
    
    function isSearch($opts) {
        if ($opts->itemids) {
            $itemidssql=sprintf("itemid IN (%s) AND",join(",",$opts->itemids));
        } else {
            $itemidssql="";
        }
        if ($opts->hostids) {
            $hostidssql=sprintf("hostid IN (%s) AND",join(",",$opts->hostids));
        } else {
            $hostidssql="";
        }
        $wids=Tw::twToIds($opts);
        if (count($wids)>0) {
            $windowidsql=sprintf("windowid IN (%s) AND",join(",",$wids));
        } else {
            return(false);
        }
        $rows=$this->mquery(
                "SELECT * from itemstat
                 WHERE $itemidssql $hostidssql $windowidsql true"
                );
        return($rows);
    }
    
    function isToIds($opts,$pkey=false) {
        $ids=$this->isSearch($opts);
        if (!$ids) {
            return(false);
        }
        $rows=$ids->fetchAll();
        $itemids=Array();
        foreach ($rows as $row) {
            if ($pkey) {
                $itemids[]=Array(
                    "itemid" => $row->itemid,
                    "windowid" => $row->windowid,
                    "hostid" => $row->hostid);
                } else {
                    $itemids[]=$row->itemid;
                }
        }
        return($itemids);
    }
        
    function isCompute($wid) {
        
        $w=Tw::twGet($wid)->fetch();
        $this->mbegin();
        $this->dbg->warn("Computing item statistics for window id $w->id (zabbix_id:$w->serverid,$w->description)\n");
        $items=$this->isToIds($this->opts);
        if (count($items)>0) {
            $itemidsql=sprintf("AND itemid IN (%s)",join($items));
        } else {
            $itemidsql="";
        }
        $this->sreset();
        $rows=$this->zcquery(
            "SELECT itemid AS itemid,
                    min(value) AS min,
                    max(value) AS max,
                    avg(value) AS avg,
                    stddev(value) AS stddev,
                    max(value)-min(value) AS delta,
                    count(*) AS cnt
                FROM history
                WHERE clock>=? and clock<? $itemidsql
                GROUP BY itemid

                UNION

                SELECT itemid AS itemid,
                    min(value) AS min,
                    max(value) AS max,
                    avg(value) AS avg,
                    stddev(value) AS stddev,
                    max(value)-min(value) AS delta,
                    count(*) AS cnt
                FROM history_uint
                WHERE clock>? and clock<? $itemidsql
                GROUP BY itemid
                ",
                $w->fstamp,$w->tstamp,$w->fstamp,$w->tstamp);
        if (count($rows)==0) {
            $d=$this->mquery("DELETE FROM itemstat WHERE windowid=?",$wid);
            $this->mquery("UPDATE timewindow
                SET updated=?, found=0, processed=0, ignored=0, lowcnt=0, lowavg=0, stddev0=0 WHERE id=?",
                New \DateTime(),
                $wid);
            $this->mcommit();
            return(false);
        }
        $hostids=Array();
        $itemids=Array();
        $rowscnt=0;
        foreach ($rows as $s) {
            $this->dbg->dbg(sprintf("Processing %d of %d items (id=%d,stddev=%.2f)\n",$rowscnt,count($rows),$s->itemid, $s->stddev));
            $this->sadd("found");
            $rowscnt++;
            $itemids[]=$s->itemid;
            if ($s->stddev == 0) {
                $this->sadd("ignored");
                $this->sadd("stddev0");
                continue;
            }
            if ($s->cnt < $this->opts->min_values_per_window) {
                $this->sadd("ignored");
                $this->sadd("lowcnt");
                continue;
            }
            if ($s->avg < $this->opts->min_avg_for_cv) {
                $this->sadd("ignored");
                $this->sadd("lowavg");
                continue;
            }
            $this->sadd("processed"); 
            $cv=$s->stddev/$s->avg;
            $d=$this->mquery("DELETE FROM itemstat WHERE windowid=? AND itemid=?",$wid,$s->itemid);
            $r=$this->mquery("INSERT INTO itemstat ",
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
        $this->mquery("UPDATE timewindow
                SET updated=?, found=?, processed=?, ignored=?, lowcnt=?, lowavg=?, stddev0=? WHERE id=?",
                New \DateTime(),
                $this->sget("found"),
                $this->sget("processed"),
                $this->sget("ignored"),
                $this->sget("lowcnt"),
                $this->sget("lowavg"),
                $this->sget("stddev0"),
                $wid);
        $this->mcommit();
        return($this->sget());
    }
    
    public function IsMultiCompute($opts) {
        if (!$opts->empty && !$opts->createdonly && !$opts->updated) {
            $this->dbg->warn("All windows will be recomputed! Consider using selector for empty or just created windows.\n");
        }
        $windows=Tw::twSearch($this->opts)->fetchAll();
        $this->dbg->warn(sprintf("Need to compute %d windows\n",count($windows)));
        foreach ($windows as $w) {
            if ($this->doJob()) {
                $stats=ItemStat::isCompute($w->id,$this->opts->itemids,$this->opts->hosts);
                if (!$stats) {
                    $this->mexit(10,"No data in history available!\n");
                } else {
                    $this->dbg->info(sprintf("Window %d: found=%d, processed=%d, ignored=%d\n",$w->id,$stats["found"],$stats["processed"],$stats["ignored"]));
                }
                $this->exitJob();
                
            }
        }
    }
    
    public function IsDelete($opts) {
        $items=$this->IsToIds($opts);
        $windowids=Tw::TwtoIds($opts);
        $this->dbg->warn(sprintf("Will delete %d itemstat entries (%d windows).\n",count($items),count($windowids)));
        if (count($items)>0 && count($windowids)>0) {
            $this->mbegin();
            $this->mquery("DELETE FROM itemstat WHERE ?", $items);
            $this->mquery("UPDATE timewindow SET updated=NULL,loi=0 WHERE id IN (?)",$windowids);
            $this->mcommit();
        }
    }
    
    public function IsShow($opts) {
        $stats=$this->mquery("
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
        if (count($wids)>0) {
            $stat=$this->mquery("
                SELECT MIN(cv) AS mincv,
                    MAX(cv) AS maxcv,
                    MIN(cnt) AS mincnt,
                    MAX(cnt) AS maxcnt
                FROM itemstat
                WHERE windowid IN (?)",$wids)->fetch();

            $lsql=$this->mquery("
                UPDATE itemstat 
                SET loi=100*(cv/?)*(cnt/?)
                WHERE windowid IN (?)
                ",$stat->maxcv,$stat->maxcnt,$wids);
        }
    }
}

?>
