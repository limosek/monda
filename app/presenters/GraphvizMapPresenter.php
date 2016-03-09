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
     
     gm:tws  [common opts]
     gm:icw [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=  IcPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "tl_items",
                false,"tl_items",
                "How many items to place in url of graphs",
                10,
                10
                );
        return($ret);
    }
    
    function renderTws() {
        $this->setLayout(false);
        $opts = $this->opts;
        $wids = \App\Model\Tw::twToIds($opts);
        $tree = \App\Model\Tw::twTree($wids);
        $stats = \App\Model\Tw::twStats($opts);
        $this->template->title = "Monda TimeWindows";
        $opts = IcPresenter::getOpts($this->opts);
        $opts->corr = "samehour";
        $ics = Model\ItemCorr::icWStats($opts)->fetchAll();
        if (sizeof($ics) > 0) {
            foreach ($ics as $ic) {
                $opts->wids = Array($ic->windowid1, $ic->windowid2);
                $opts->max_rows = 10;
                $itemids = Model\ItemCorr::icToIds($opts, false, true);
                $wcorr[$ic->windowid1][$ic->windowid2] = $ic->acorr * 10;
                $w1 = Model\Tw::twGet($ic->windowid1);
                $w2 = Model\Tw::twGet($ic->windowid2);
                $urls[$ic->windowid1][$ic->windowid2] = self::ZabbixGraphUrl1($itemids, $w1->fstamp, $w1->seconds);
                $urls[$ic->windowid2][$ic->windowid1] = self::ZabbixGraphUrl1($itemids, $w2->fstamp, $w2->seconds);
            }
            $this->template->wcorr = $wcorr;
            $this->template->urls = $urls;
        }
        $twids = Array();
        foreach ($wids as $w) {
            if (!array_key_exists($w, $twids)) {
                $twids[$w] = \App\Model\Tw::twGet($w);
            }
        }
        $map = self::TwTreeMap($tree, $twids, $stats, false, $opts->mapname);
        $this->template->map = $map;
        $this->template->stats = $stats;
    }

    function renderIcw() {
        $opts=IcPresenter::getOpts($this->opts);
        $this->setLayout(false);
        $this->template->title = "Monda Correlations";
        $tws=Model\Tw::twToIds($this->opts);
        if (sizeof($tws)>1) {
            self::mexit(2,"Specify only one window!\n");
        }
        $ics=  Model\ItemCorr::icQuickSearch($this->opts)->fetchAll();
        if (sizeof($ics)==0) {
            self::mexit(2,"No correlations to report?\n");
        }
        $window=  Model\Tw::twGet($tws);
        foreach ($ics as $i=>$ic) {
            if ($ic->itemid1==$ic->itemid2) continue;
            $ics[$i]->size=$ic->corr*5;
            $ics[$i]->label=sprintf("%.2f",$ic->corr);
            $info1=Model\ItemStat::iteminfo($ic->itemid1);
            $info2=Model\ItemStat::iteminfo($ic->itemid2);
            $iteminfo[$ic->itemid1]= $info1[0];
            $iteminfo[$ic->itemid2]= $info2[0];
            $hostid1=$info1[0]->hostid;
            $hostid2=$info2[0]->hostid;
            $hosts[$hostid1][]=$ic->itemid1;
            $hosts[$hostid2][]=$ic->itemid2;
            if ($opts->outputverb=="expanded") {
                $hostsinfo[$hostid1]=  HsPresenter::expandHost($info1[0]->hostid);
                $hostsinfo[$hostid2]=  HsPresenter::expandHost($info2[0]->hostid);
                $itemsinfo[$ic->itemid1]=  addslashes(IsPresenter::expandItem($ic->itemid1,false,true));
                $itemsinfo[$ic->itemid2]=  addslashes(IsPresenter::expandItem($ic->itemid2,false,true));
                $ics[$i]->url=self::ZabbixGraphUrl2(Array($ic->itemid1,$ic->itemid2),$window->fstamp,$window->seconds);
                $iurl[$ic->itemid1]=self::ZabbixGraphUrl1(Array($ic->itemid1),$window->fstamp,$window->seconds);
                $iurl[$ic->itemid2]=self::ZabbixGraphUrl1(Array($ic->itemid2),$window->fstamp,$window->seconds);
            } else {
                $hostsinfo[$hostid1]=$info1[0]->hostid;
                $hostsinfo[$hostid2]=$info2[0]->hostid;
                $itemsinfo[$ic->itemid1]=  $ic->itemid1;
                $itemsinfo[$ic->itemid2]=  $ic->itemid2;
                $ics[$i]->url="";
            }
        }
        $this->template->wid=$tws;
        $this->template->hosts=$hosts;
        $this->template->hostsinfo=$hostsinfo;
        $this->template->itemsinfo=$itemsinfo;
        $this->template->iurl=$iurl;
        $this->template->ics=$ics;
        $this->template->window=$window;
    }

}
