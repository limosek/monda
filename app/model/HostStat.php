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
class HostStat extends Tw {
    
    function hsToIds($opts) {
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
    
    function hsCompute($hostid,$opts) {
        $itemids=$this->host2itemids($hostid);
        if (count($itemids)<1) {
            return(false);
        }
        $opts->empty=false;
        $wids=$this->twToIds($opts);
        if (count($wids)==0) {
            return(false);
        }
        $this->dbg->warn(sprintf("Need to compute HostStat for host %s (%d windows).\n",$hostid,count($wids)));
        $i=0;
        foreach ($wids as $wid) {
            $this->mbegin();
            $i++;
            $this->dbg->info(sprintf("Computing HostStat for host %s and window %s (%d of %d)\n",$hostid,$wid,$i,count($wids)));
            //$hs=$this->mquery("SELECT COUNT(*) AS cnt FROM hoststat WHERE windowid=? AND hostid=?",$wid,$hostid)->fetch();
            $ius=$this->mquery("
                UPDATE itemstat
                SET hostid=?
                WHERE itemid IN (?) AND windowid=? AND hostid IS NULL",
                $hostid,$itemids,$wid);
            $stat=$this->mquery("
                SELECT AVG(cv) AS cv,
                    SUM(loi) AS loi,
                    COUNT(itemid) AS itemid,
                    COUNT(cnt) AS cnt
                FROM itemstat
                WHERE windowid=? AND hostid IN (?) 
                GROUP BY hostid
                ",$wid,$hostid);
            $row=$stat->fetch();
            $sd=$this->mquery("DELETE FROM hoststat WHERE windowid=? AND hostid=?",
                    $wid,$hostid
                    );
            $su=$this->mquery("
                INSERT INTO hoststat",
                    Array(
                        "hostid" => $hostid,
                        "windowid" => $wid,
                        "cnt" => $row->cnt,
                        "loi" => $row->loi,
                        "updated" => New \DateTime()
                        )
                );
            $this->mcommit();
        }
    }
    
}

?>
