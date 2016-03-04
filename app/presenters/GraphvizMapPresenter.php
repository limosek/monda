<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class GraphvizMapPresenter extends MapPresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     Graphviz Map operations
     
     gm:tl  [common opts]
     gm:hs [common opts]
     gm:icw [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    function renderTl() {
        $this->setLayout(false);
        $this->template->title = "Monda TimeWindow Hierarchy";
        parent::renderTl();
    }

    function renderHs() {
        $this->setLayout(false);
        $this->template->title = "Monda Hosts Stats";
        parent::renderHs();
    }
    
    function renderIcw() {
        $opts=IcPresenter::getOpts($this->opts);
        $this->setLayout(false);
        $this->template->title = "Monda Correlations";
        $ics=  Model\ItemCorr::icSearch($this->opts)->fetchAll();
        $windowids=Array();
        $itemids=Array();
        $ictable=Array();
        foreach ($ics as $ic) {
            if ($ic->itemid1==$ic->itemid2) continue;
            $ic->size=$ic->icloi*$this->opts->loi_sizefactor+$this->opts->loi_minsize;
            $itemids[$ic->itemid1]=$ic->itemid1;
            $itemids[$ic->itemid2]=$ic->itemid1;
            $windowids[$ic->itemid1]=$ic->icwindowid1;
            $windowids[$ic->itemid2]=$ic->icwindowid2;
            $ictable[$ic->icwindowid1][$ic->icwindowid2][$ic->itemid1][$ic->itemid2]=$ic;
        }
        $this->template->windowids=$windowids;
        $this->template->itemids=$itemids;
    }

}
