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
     is:stats [common opts]
     is:history [common opts]
     is:compute [common opts]
     is:delete [common opts]
     is:loi [common opts]

     [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=BasePresenter::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=$this->parseOpt($ret,
                "min_values_per_window",
                "Mvw","min_values_per_window",
                "Minimum values for item per window to process",
                20,
                20
                );
        $ret=$this->parseOpt($ret,
                "min_avg_for_cv",
                "Mac","min_avg_for_cv",
                "Minimum average for CV to process",
                0.01,
                0.01
                );
        $ret=$this->parseOpt($ret,
                "min_stddev",
                "Msd","min_stddev",
                "Minimum stddev of values to process. Only bigger stddev will be processed",
                0,
                0
                );
        $ret=$this->parseOpt($ret,
                "min_cv",
                "Mcv","min_cv",
                "Minimum CV to process values.",
                0.01,
                0.01
                );
        $ret=$this->parseOpt($ret,
                "max_cv",
                false,"max_cv",
                "Maximum CV to process values.",
                100,
                100
                );
        $ret=$this->parseOpt($ret,
                "itemids",
                "Ii","itemids",
                "Itemids to get",
                false,
                "All"
                );
        $ret=$this->parseOpt($ret,
                "max_windows_per_query",
                false,"max_windows_per_query",
                "Maximum number of windows per one sql query",
                10,
                10
                );
        if ($ret->itemids) {
            $ret->itemids=preg_split("/,/",$ret->itemids);
        }
        $ret=$this->parseOpt($ret,
                "items",
                "Ik","items",
                "Item keys to get. Use ~ to add more items. Prepend item by @ to use regex.",
                false,
                "All"
                );
        if ($ret->items) {
            $ret->items=preg_split("/~/",$ret->items);
        }
        $ret=\App\Model\ItemStat::itemsToIds($ret);
        if (is_array($ret->itemids)) {
            \App\Model\CliDebug::info(sprintf("Itemids selected: %s\n",join(",",$ret->itemids)));
        }
        return($ret);
    }
    
    public function renderIs() {
        self::Help();
        self::mexit();
    }
    
    public function expandItem($itemid,$withhost=false,$desc=false) {
        $ii=\App\Model\ItemStat::itemInfo($itemid);
        if (count($ii)>0) {
            if ($desc) {
                $itxt=$ii[0]->name;
            } else {
                $itxt=$ii[0]->key_;
            }
            if ($withhost) {
                return(HsPresenter::expandHost($ii[0]->hostid).":".$itxt);
            } else {
                return($itxt);
            }
        } else {
            return("unknown");
        }
    }

    public function renderShow() {
        $rows=\App\Model\ItemStat::isSearch($this->opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            if ($this->opts->outputverb=="expanded") {
                foreach ($this->exportdata as $i=>$row) {
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
    
    public function renderHistory() {
        $rows = \App\Model\ItemStat::isZabbixHistory($this->opts);
        if ($rows) {
            $this->exportdata=array_values($rows);
            //dump($rows);exit;
            parent::renderShow($this->exportdata);
        }
    }

    public function renderStats() {
        $rows=\App\Model\ItemStat::isStats($this->opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            if ($this->opts->outputverb=="expanded") {
                foreach ($this->exportdata as $i=>$row) {
                    \App\Model\CliDebug::dbg(sprintf("Processing %d row of %d          \r",$i,count($this->exportdata)));
                    $row["key"]=self::expandItem($row->itemid,true);
                    $this->exportdata[$i]=$row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }
    
    public function renderLoi() {
        \App\Model\ItemStat::IsLoi($this->opts);
        self::mexit();
    }
    
    public function renderCompute() {
        \App\Model\ItemStat::IsMultiCompute($this->opts);
        self::mexit(0,"Done\n");
    }
    
    public function renderDelete() {
        \App\Model\ItemStat::IsDelete($this->opts);
        self::mexit(0,"Done\n");
    }
}