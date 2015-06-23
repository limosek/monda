<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    Nette\Utils\DateTime as DateTime,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class HostStat extends Monda {
    
    static function hostGroupsToIds($hostgroups) {
        $hq=Array(
            "selectHosts" => "refer",
            "output" => "extend",
            "filter" => Array(
                    "name"=> $hostgroups
                )
            );
        $hg=Monda::apiCmd("hostGroupGet",$hq);
        $hostids=Array();
        if (count($hg)>0) {
            foreach ($hg as $hostgroup) {
                foreach ($hostgroup->hosts as $host) {
                    $hostids[]=$host->hostid;
                }
            }
        } else {
            \App\Presenters\BasePresenter::mexit(22,"Hostgroup(s) to fetch does not exists!\n");
        }
        return($hostids);
    }
    
    static function hostsToIds($opts) {
        if (!is_array($opts->hostids)) {
            $opts->hostids=Array();
        }
        if ($opts->hostgroups) {
            $opts->hostids=Array_merge($opts->hostids,self::hostGroupsToIds($opts->hostgroups));
        }
        if ($opts->hosts) {
            $iq = Array(
                "monitored" => true
            );
            $iq["filter"]["host"] = $opts->hosts;
            $h = Monda::apiCmd("hostGet",$iq);
            if (count($h)>0) {
                foreach ($h as $host) {
                    $opts->hostids[]=$host->hostid;
                }
            } else {
                \App\Presenters\BasePresenter::mexit(22,"Host(s) to fetch does not exists!\n");
            }
        }
        return($opts);
    }
    
    static function hostids2itemids($hostids) {
        $itemids=Array();
        $c=1;
        $hostcount=count($hostids);
        foreach ($hostids as $hostid) {
            $iq = Array(
                "monitored" => true,
                "hostids" =>  Array($hostid)
            );
            CliDebug::dbg("Querying items ($c of $hostcount hosts)\n");
            $i=Monda::apiCmd("itemGet",$iq);
            if (count($i)>0) {
                    foreach ($i as $item) {
                        $itemids[]=$item->itemid;
                    }
            }
            $c++;
        }
        return($itemids);
    }
    
    static function hosts2ids($hosts) {
        $iq = Array(
                "monitored" => true,
                "filter" => Array(
                    "name" => $hosts
                    )
            );
        $hs=Monda::apiCmd("hostGet",$iq);
        $ret=Array();
        foreach ($hs as $h) {
            $ret[$h->host]=$h->hostid;
        }
        return($ret);
    }
    
    static function host2id($host) {
        $h=self::hosts2ids(Array($host));
        return($h[$host]);
    }
    
    static function hsSearch($opts) {
        $wids=Tw::twToIds($opts);
        if (count($wids)==0) {
            return(false);
        }
        $ids=self::mquery("
            SELECT
              hoststat.hostid AS hostid,
              hoststat.windowid AS windowid,
              hoststat.cnt AS cnt,
              hoststat.loi AS loi
            FROM hoststat
            WHERE
             hoststat.windowid IN (?)
             AND hoststat.hostid IN (?)
             ORDER BY hoststat.loi DESC
            ",
                $wids,
                $opts->hostids);
        return($ids);
    }
    
    static function hsToIds($opts,$pkey=false) {
        $ids=self::hsSearch($opts);
        if (!$ids) {
            return(false);
        }
        $rows=$ids->fetchAll();
        $hostids=Array();
        foreach ($rows as $row) {
            if ($pkey) {
                $hostids[]=Array(
                    "hostid" => $row->hostid,
                    "windowid" => $row->windowid
                    );
                } else {
                    $hostids[]=$row->hostid;
                }
        }
        return($hostids);
    }
    
    static function hsUpdate($opts) {
        $hostids=$this->opts->hostids;
        $itemids=self::hostids2itemids($hostids);
        $wids=Tw::twToIds($opts);
        CliDebug::warn(sprintf("Need to update HostStat for %d windows, %d hosts and %d items.\n",count($wids),count($hostids),count($itemids)));
        if (count($wids)==0 || count($hostids)<1 || count($itemids)<1) {
            return(false);
        }
        self::mbegin();
        foreach ($hostids as $hostid) {
            $hitemids=self::hostids2itemids(array($hostid));
            if (count($hitemids)<1) continue;
            $ius=self::mquery("
                UPDATE itemstat
                SET hostid=?
                WHERE itemid IN (?) AND windowid IN (?) AND hostid IS NULL",
                    $hostid,
                    $hitemids,
                    $wids);
        }
        self::mcommit();
    }
    
    static function hsDelete($opts) {
        $ids=self::hsToIds($opts,true);
        self::mbegin();
        foreach ($ids as $id) {
            $dq=self::mquery("DELETE FROM hoststat WHERE ",$id);
        }
        self::mcommit();
    }
    
    static function hsMultiCompute($opts) {
        $wids=Tw::twToIds($opts);
        CliDebug::warn(sprintf("Need to compute HostStat for %d windows.\n",count($wids)));
        if (count($wids)==0 || count($opts->hostids)==0) {
            return(false);
        }
        $stat=self::mquery("
            SELECT itemstat.hostid AS hostid,
                itemstat.windowid AS windowid,
                AVG(cv) AS cv,
                SUM(itemstat.loi) AS loi,
                COUNT(itemid) AS itemid,
                COUNT(itemstat.cnt) AS cnt
            FROM itemstat
            LEFT JOIN hoststat ON (hoststat.hostid=itemstat.hostid)
            WHERE itemstat.windowid IN (?) AND itemstat.hostid IN (?)
              AND itemstat.cnt>0
            GROUP BY itemstat.hostid,itemstat.windowid
            ",$wids,$opts->hostids);
        $rows=$stat->fetchAll();
        $i=0;
        foreach ($rows as $row) {
            self::mbegin();
            $i++;
            CliDebug::info(sprintf("Computing HostStat for host %s and window %s (%d of %d)\n",$row->hostid,$row->windowid,$i,count($rows)));
            $sd=self::mquery("DELETE FROM hoststat WHERE windowid=? AND hostid=?",
                    $row->windowid,$row->hostid
                    );
            $su=self::mquery("
                INSERT INTO hoststat",
                    Array(
                        "hostid" => $row->hostid,
                        "windowid" => $row->windowid,
                        "cnt" => $row->cnt,
                        "loi" => 0,
                        "updated" => New DateTime()
                        )
                );
            self::mcommit();
        }
    }
    
    static function hsLoi($opts) {
        $wids=Tw::twToIds($opts);
        CliDebug::warn(sprintf("Need to compute HostStat Loi on %d windows.\n",count($wids)));
        if (count($wids)==0) {
            return(false);
        }
        self::mbegin();
        $stats=self::mquery("SELECT
                  windowid,
                  MAX(cnt) AS maxcnt,
                  MIN(cnt) AS mincnt
                FROM hoststat
                WHERE windowid IN (?)
                GROUP BY windowid",Tw::twToIds($opts))->fetchAll();
        foreach ($stats as $s) {
            foreach ($opts->hostids as $hostid) {
                $lq=self::mquery("UPDATE hoststat set loi=100*cnt/? WHERE windowid=? AND hostid=?",$s->maxcnt,$s->windowid,$hostid);
            }
            
        }
        self::mcommit();
    }
    
}

?>
