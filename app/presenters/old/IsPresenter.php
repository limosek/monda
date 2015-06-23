<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class IsPresenter extends BasePresenter
{
    
    public function Help() {
        \App\Model\CliDebug::warn("
     ItemStats operations
            
     is:show [common opts]
     is:compute [common opts]
     is:delete [common opts]
     is:loi [common opts]
     is:stats [common opts]

     [common opts]
    \n");
        self::helpOpts();
    }
    
    static function getOpts($ret) {
        $ret=BasePresenter::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "min_values_per_window",
                "Mvw","min_values_per_window",
                "Minimum values for item per window to process",
                20,
                20
                );
        $ret=self::parseOpt($ret,
                "min_avg_for_cv",
                "Mac","min_avg_for_cv",
                "Minimum average for CV to process",
                0.01,
                0.01
                );
        $ret=self::parseOpt($ret,
                "min_stddev",
                "Msd","min_stddev",
                "Minimum stddev of values to process. Only bigger stddev will be processed",
                0,
                0
                );
        $ret=self::parseOpt($ret,
                "min_cv",
                "Mcv","min_cv",
                "Minimum CV to process values.",
                0.01,
                0.01
                );
        $ret=self::parseOpt($ret,
                "itemids",
                "Ii","itemids",
                "Itemids to get",
                false,
                "All"
                );
        $ret=self::parseOpt($ret,
                "max_windows_per_query",
                false,"max_windows_per_query",
                "Maximum number of windows per one sql query",
                10,
                10
                );
        if ($ret->itemids) {
            $ret->itemids=preg_split("/,/",$ret->itemids);
        }
        $ret=self::parseOpt($ret,
                "items",
                "Ik","items",
                "Item keys to get. Use host: to get all items of host. Use @HostGroup: to get all items of hostgroup.",
                false,
                "All"
                );
        if ($ret->items) {
            $ret->items=preg_split("/,/",$ret->items);
        }
        $ret=self::parseOpt($ret,
                "max_items",
                "Im","max_items",
                "Maximum number of items to get (LIMIT for SELECT)",
                false,
                "All"
                );
        $ret=self::parseOpt($ret,
                "isloionly",
                "ISL","itemstat_with_loi",
                "Search only items with loi>0",
                false,
                false
                );
        $ret=\App\Model\ItemStat::itemsToIds($ret);
        if (is_array($ret->itemids)) {
            \App\Model\CliDebug::dbg(sprintf("Itemids selected: %s\n",join(",",$ret->itemids)));
        }
        return($ret);
    }
    
    public function renderIs() {
        self::Help();
        self::mexit();
    }
    
    static public function expandItem($itemid,$withhost=false) {
        $ii=\App\Model\ItemStat::itemInfo($itemid);
        if (count($ii)>0) {
            if ($withhost) {
                return(HsPresenter::expandHost($ii[0]->hostid).":".$ii[0]->key_);
            } else {
                return($ii[0]->key_);
            }
        } else {
            return("unknown");
        }
    }

    public function renderShow($var) {
        $rows=\App\Model\ItemStat::isSearch(\App\Model\Monda::$opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            if (\App\Model\Monda::$opts->outputverb=="expanded") {
                $i=0;
                foreach ($this->exportdata as $i=>$row) {
                    $i++;
                    \App\Model\CliDebug::dbg(sprintf("Processing %d row of %d          \r",$i,count($this->exportdata)));
                    $row["host"]=HsPresenter::expandHost($row->hostid);
                    $row["key"]=self::expandItem($row->itemid);
                    $this->exportdata[$i]=$row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }
    
    public function renderZabbixHistory() {
        if (!\App\Model\Monda::$opts->wids) {
            self::mexit(33,"No windows selected!\n");
        }
        $opts=\App\Model\ItemStat::itemsToIds(\App\Model\Monda::$opts);
        if (!\App\Model\Monda::$opts->itemids) {
            self::mexit(33,"No items selected!\n");
        }
        $ckey=serialize($opts);
            $ret=\App\Model\Monda::$sqlcache->load($ckey);
        if ($ret===null) {
            $ret=\App\Model\ItemStat::isZabbixGetHistory($opts);
            \App\Model\Monda::$sqlcache->save($ckey,
                    $ret,
                    array(
                        \Nette\Caching\Cache::EXPIRE => \App\Model\Monda::$opts->sqlcacheexpire,
                        )
                    );
        }
        parent::renderShow($ret);
        self::mexit();
    }
    
    public function renderStats() {
        $rows=\App\Model\ItemStat::isStats(\App\Model\Monda::$opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            if (\App\Model\Monda::$opts->outputverb=="expanded") {
                $i=0;
                foreach ($this->exportdata as $i=>$row) {
                    $i++;
                    \App\Model\CliDebug::dbg(sprintf("Processing %d row of %d          \r",$i,count($this->exportdata)));
                    $row["key"]=self::expandItem($row->itemid);
                    $this->exportdata[$i]=$row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }
    
    public function renderLoi() {
        \App\Model\ItemStat::IsLoi(\App\Model\Monda::$opts);
        self::mexit();
    }
    
    public function renderCompute() {
        \App\Model\ItemStat::IsMultiCompute(\App\Model\Monda::$opts);
        self::mexit(0,"Done\n");
    }
    
    public function renderDelete() {
        \App\Model\ItemStat::IsDelete(\App\Model\Monda::$opts);
        self::mexit(0,"Done\n");
    }
}