<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class IcPresenter extends BasePresenter
{
    
    public function Help() {
        \App\Model\CliDebug::warn("
     ItemCorr operations
            
     ic:show [common opts]
     ic:matrix [common opts]
     ic:compute [common opts]
     ic:delete [common opts]
     ic:loi [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=IsPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "corr",
                "Cr","corr",
                "Selector for windows to correlate with basic windows",
                "samewindow",
                "samewindow",
                Array("samewindow","samehour","samedow")
                );
        switch ($ret->corr) {
            case "samehour":
                $ret->length=Array(3600);
                break;
            case "samedow":
                $ret->length=Array(3600*24);
                break;
        }
        $ret=self::parseOpt($ret,
                "icempty",
                "ICm","only_empty_corr",
                "Work only on results which are empty (skip already computed objects)",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "icloionly",
                "ICL","only_corr_with_loi",
                "Select only objects which have loi>0",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "maxicrows",
                "ICmr","max_itemcorr_rows",
                "Maximum rows returned by cross-item searching.",
                250,
                250
                );
        $ret=self::parseOpt($ret,
                "timeprecision",
                "Tp","time_precision",
                "Time precision (maximum difference in time for correlation) in seconds",
                5,
                5
                );
        $ret=self::parseOpt($ret,
                "min_icvalues",
                "ICmv","min_values_for_corr",
                "Minimum values to make correlation",
                40,
                40
                );
        return($ret);
    }
    
    public function renderIc() {
        self::Help();
        self::mexit();
    }

    public function renderShow() {
        $opts=$this->opts;
        $opts->empty=false;
        $opts->icempty=false;
        $rows=  \App\Model\ItemCorr::icQuickSearch($opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            if ($this->opts->outputverb=="expanded") {
                $i=0;
                foreach ($this->exportdata as $i=>$row) {
                    $i++;
                    \App\Model\CliDebug::dbg(sprintf("Processing %d row of %d                 \r",$i,count($this->exportdata)));
                    $row["key1"]= IsPresenter::expandItem($row->itemid1,true);
                    $row["key2"]= IsPresenter::expandItem($row->itemid2,true);
                    $row["window1"]= TwPresenter::expandTw($row->windowid1);
                    $row["window2"]= TwPresenter::expandTw($row->windowid2);
                    $this->exportdata[$i]=$row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }
    
    public function renderMatrix() {
        $opts = $this->opts;
        $opts->empty = false;
        $opts->icempty = false;
        $rows = \App\Model\ItemCorr::icQuickSearch($opts);
        $m = Array();
        $cnt = Array();
        $itemids = Array();
        if ($rows) {
            $rows = $rows->fetchAll();
            if (sizeof($opts->wids) == 1) {
                foreach ($rows as $r) {
                    $m[$r->itemid1][$r->itemid2] = $r->corr;
                    $m[$r->itemid2][$r->itemid1] = $r->corr;
                    $itemids[$r->itemid1] = true;
                    $itemids[$r->itemid2] = true;
                }
            } elseif (sizeof($opts->wids) == 2) {
                foreach ($rows as $r) {
                    if ($r->windowid1 != $r->windowid2) {
                        $m[$r->itemid1][$r->itemid2] = $r->corr;
                        $m[$r->itemid2][$r->itemid1] = $r->corr;
                        $itemids[$r->itemid1] = true;
                        $itemids[$r->itemid2] = true;
                    }
                }
            } else {
                self::mexit(1, "Must be one window or two windows (-w)\n");
            }
        }
        foreach ($itemids as $i => $v) {
            foreach ($itemids as $j => $v) {
                if (!isset($m[$i][$j])) {
                    if ($i == $j) {
                        echo "1 ";
                    } else {
                        echo "0 ";
                    }
                } else {
                    echo $m[$i][$j] . " ";
                }
            }
            echo "\n";
        }
        self::mexit();
    }

    public function renderLoi() {
        \App\Model\ItemCorr::IcLoi($this->opts);
        self::mexit();
    }
    
    public function renderCompute() {
        \App\Model\ItemCorr::IcMultiCompute($this->opts);
        self::mexit(0,"Done\n");
    }
    
    public function renderDelete() {
        \App\Model\ItemCorr::IcDelete($this->opts);
        self::mexit(0,"Done\n");
    }
}