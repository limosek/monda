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
class HostStat extends Monda {
    
    function hostsToIds($opts) {
        if (!is_array($opts->hostids)) {
            $opts->hostids=Array();
        }
        if ($opts->hostgroups) {
            $hq=Array(
                "selectHosts" => "refer",
                "output" => "extend",
                "filter" => Array(
                        "name"=> $opts->hostgroups
                    )
                );
            $hg=$this->apiCmd("hostGroupGet",$hq);
            foreach ($hg as $hostgroup) {
                foreach ($hostgroup->hosts as $host) {
                    $opts->hostids[]=$host->hostid;
                }
            }
        }
        if ($opts->hosts) {
            $iq = Array(
                "monitored" => true
            );
            $iq["filter"]["host"] = $opts->hosts;
            $h = $this->apiCmd("hostGet",$iq);
            foreach ($h as $host) {
                $opts->hostids[]=$host->hostid;
            }
        }
        return($opts);
    }
    
    function host2itemids($hostid) {
        $iq = Array(
                "monitored" => true,
                "selectItems" => "refer",
                "hostids" =>  Array($hostid)
            );
        $h=$this->apiCmd("hostGet",$iq);
        $itemids=Array();
        foreach ($h[0]->items as $item) {
            $itemids[]=$item->itemid;
        } 
        return($itemids);
    }
    
    function host2id($host) {
        $iq = Array(
                "monitored" => true,
                "filter" => Array(
                    "name" => Array($host)
                    )
            );
        $h=$this->apiCmd("hostGet",$iq);
        return($h->hostid);
    }
    
    function hsSearch($opts) {
        $wids=Tw::twToIds($opts);
        if (count($wids)==0) {
            return(false);
        }
        if (is_numeric($opts->updated)) {
            $updatedflag=false;
            $updated=$opts->updated;
        } elseif ($opts->updated==false) {
            $updatedflag=true;
            $updated=0;
        } else {
            $updated=time();
        }
        if ($opts->createdonly) { 
            $createdsql="updated IS NULL";
        } else {
            $createdsql="true";
        }
        $ids=$this->mquery("SELECT *
            FROM hoststat
            WHERE
             (updated<? OR ?)
             AND $createdsql
             AND windowid IN (?)
             AND hostid IN (?)
            ",
                New \DateTime("@" . $updated),
                $updatedflag,
                $wids,
                $opts->hostids);
        return($ids);
    }
    
    function hsToIds($opts,$pkey=false) {
        $ids=$this->hsSearch($opts);
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
    
    function hsUpdate($opts) {
        $hsids=$this->hsToIds($opts,true);
        if (!$hsids || count($hsids)<1) {
            return(false);
        }
        $this->mbegin();
        foreach ($hsids as $hs) {
            $this->dbg->info(sprintf("Updating hostid for hostid %s and windowid %d\n",$hs["hostid"],$hs["windowid"]));
            $itemids=$this->host2itemids($hs["hostid"]);
            $ius=$this->mquery("
                UPDATE itemstat
                SET hostid=?
                WHERE itemid IN (?) AND windowid=? AND hostid IS NULL",
                    $hs["hostid"],
                    $itemids,
                    $hs["windowid"]);
        }
        $this->mcommit();
    }
    
    function hsDelete($opts) {
        $ids=$this->hsToIds($opts,true);
        $this->mbegin();
        foreach ($ids as $id) {
            $dq=$this->mquery("DELETE FROM hoststat WHERE ",$id);
        }
        $this->mcommit();
    }
    
    function hsMultiCompute($opts) {
        $wids=Tw::twToIds($opts);
        if (count($wids)==0) {
            return(false);
        }
        $stat=$this->mquery("
            SELECT itemstat.hostid AS hostid,
                itemstat.windowid AS windowid,
                AVG(cv) AS cv,
                SUM(itemstat.loi) AS loi,
                COUNT(itemid) AS itemid,
                COUNT(itemstat.cnt) AS cnt
            FROM itemstat
            LEFT JOIN hoststat ON (hoststat.hostid=itemstat.hostid)
            WHERE itemstat.windowid IN (?) AND itemstat.hostid IN (?)
              AND hoststat.updated IS NULL
              AND itemstat.cnt>0
            GROUP BY itemstat.hostid,itemstat.windowid
            ",$wids,$opts->hostids);
        $rows=$stat->fetchAll();
        $this->dbg->warn(sprintf("Need to compute HostStat for %d rows.\n",count($rows)));
        $i=0;
        foreach ($rows as $row) {
            $this->mbegin();
            $i++;
            $this->dbg->info(sprintf("Computing HostStat for host %s and window %s (%d of %d)\n",$row->hostid,$row->windowid,$i,count($rows)));
            $sd=$this->mquery("DELETE FROM hoststat WHERE windowid=? AND hostid=?",
                    $row->windowid,$row->hostid
                    );
            $su=$this->mquery("
                INSERT INTO hoststat",
                    Array(
                        "hostid" => $row->hostid,
                        "windowid" => $row->windowid,
                        "cnt" => $row->cnt,
                        "loi" => $row->loi,
                        "updated" => New \DateTime()
                        )
                );
            $this->mcommit();
        }
    }
    
    function hsLoi() {
        
    }
    
}

?>
