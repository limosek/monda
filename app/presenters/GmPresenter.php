<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    App\Model\Opts,
    App\Model\Tw,
    App\Model\Util,
    App\Model\ItemStat,
    App\Model\ItemCorr,
    App\Model\CliDebug,
    Nette\Utils\DateTime as DateTime;

class GmPresenter extends MapPresenter {

    public function Help() {
        CliDebug::warn("
     Graphviz Map operations
     
     gm:tws  [common opts]
     gm:icw [common opts]
 
     [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }
    
    public function renderGm() {
        self::Help();
        self::mexit();
    }
    
    public function startup() {
        parent::startup();
    }
    
    function renderTws() {
        $this->setLayout(false);
        $wids = Tw::twToIds();
        $tree = Tw::twTree($wids);
        $stats = Tw::twStats();
        $this->template->title = "Monda TimeWindows";
        Opts::setOpt("corr_type","samehour");
        $ics = ItemCorr::icTwStats()->fetchAll();
        if (sizeof($ics) > 0) {
            foreach ($ics as $ic) {
                Opts::setOpt("window_ids",Array($ic->windowid1, $ic->windowid2));
                Opts::setOpt("max_rows",10);
                $itemids = ItemCorr::icToIds(false, true);
                $wcorr[$ic->windowid1][$ic->windowid2] = $ic->acorr * 10;
                $w1 = Tw::twGet($ic->windowid1);
                $w2 = Tw::twGet($ic->windowid2);
                $urls[$ic->windowid1][$ic->windowid2] = Util::ZabbixGraphUrl1($itemids, $w1->fstamp, $w1->seconds);
                $urls[$ic->windowid2][$ic->windowid1] = Util::ZabbixGraphUrl1($itemids, $w2->fstamp, $w2->seconds);
            }
            $this->template->wcorr = $wcorr;
            $this->template->urls = $urls;
        }
        $twids = Array();
        foreach ($wids as $w) {
            if (!array_key_exists($w, $twids)) {
                $twids[$w] = Tw::twGet($w);
            }
        }
        $map = self::TwTreeMap($tree, $twids, $stats);
        $this->template->map = $map;
        $this->template->stats = $stats;
    }

    function renderIcw() {
        
        $this->setLayout(false);
        $this->template->title = "Monda Correlations";
        $tws=Tw::twToIds();
        if (sizeof($tws)>1) {
            self::mexit(2,"Specify only one window!\n");
        }
        $ics=  ItemCorr::icSearch()->fetchAll();
        if (sizeof($ics)==0) {
            self::mexit(2,"No correlations to report?\n");
        }
        $window= Tw::twGet($tws);
        $iccomb=Array();
        foreach ($ics as $i=>$ic) {
            if ($ic->itemid1==$ic->itemid2) {
                unset($ics[$i]);
                continue;
            }
            if (array_key_exists($ic->itemid2.$ic->itemid1,$iccomb)) {
                unset($ics[$i]);
                continue;
            }
            $iccomb[$ic->itemid1.$ic->itemid2]=1;
            $ics[$i]->size=$ic->corr*5;
            $ics[$i]->label=sprintf("%.2f",$ic->corr);
            $info1=ItemStat::iteminfo($ic->itemid1);
            $info2=ItemStat::iteminfo($ic->itemid2);
            $iteminfo[$ic->itemid1]= $info1[0];
            $iteminfo[$ic->itemid2]= $info2[0];
            $hostid1=$info1[0]->hostid;
            $hostid2=$info2[0]->hostid;
            $hosts[$hostid1][]=$ic->itemid1;
            $hosts[$hostid2][]=$ic->itemid2;
            if (Opts::getOpt("output_verbosity")=="expanded") {
                $hostsinfo[$hostid1]=  HsPresenter::expandHost($info1[0]->hostid);
                $hostsinfo[$hostid2]=  HsPresenter::expandHost($info2[0]->hostid);
                $itemsinfo[$ic->itemid1]=  addslashes(IsPresenter::expandItem($ic->itemid1,true,true));
                $itemsinfo[$ic->itemid2]=  addslashes(IsPresenter::expandItem($ic->itemid2,true,true));
                $ics[$i]->url=Util::ZabbixGraphUrl2(Array($ic->itemid1,$ic->itemid2),$window->fstamp,$window->seconds);
                $iurl[$ic->itemid1]=Util::ZabbixGraphUrl1(Array($ic->itemid1),$window->fstamp,$window->seconds);
                $iurl[$ic->itemid2]=Util::ZabbixGraphUrl1(Array($ic->itemid2),$window->fstamp,$window->seconds);
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
        dump($ics);
        $this->template->window=$window;
    }

}
