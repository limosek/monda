<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime,
    Tree\Node\Node;

class HtmlPresenter extends MapPresenter {
    
    var $testdata;

    public function Help() {
        \App\Model\CliDebug::warn("
     HTML Map operations
            
     hm:tw -w id [common opts] Information about timewindow
     hm:month [common opts] Monthly information about timewindows
     hm:year [common opts] Yearly information about timewindows
     hm:tl [common opts] Timewindows tree
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    function beforeRender() {
        #if ($this->isAjax()) {
        #    \Nette\Diagnostics\Debugger::$bar = FALSE;
        #}
    }

    function renderTl() {
        $this->template->title = "Monda Timeline";
        parent::renderTl();
    }
    
    function renderMonth() {
        $this->template->title = "Monda month overview";
        parent::renderMonth();
    }
    
    function handleitemInfo($w)
    {
        Model\Monda::$opts->wids=Array($w);
        $this->testdata = 'changed value via ajax';
        if ($this->isAjax()) {
            $this->invalidateControl('iteminfo');
        }
    }
    
    function renderTw() {
        $opts=  Model\Monda::$opts;
        if (!$opts->wids || count($opts->wids)>1) {
            self::mexit(2,"Bad window ids!\n");
        }
        $map=self::createMap($opts->mapname);
        $items= \App\Model\ItemStat::IsSearch($opts);
        if (!$items) {
            self::mexit(2,"No items!\n");
        }
        $w=\App\Model\Tw::twGet($opts->wids);
        //$ec=\App\Model\EventCorr::ecSearch($opts);
        //dump($ec);
        $this->template->w=$w;
        $this->template->wid=$w->id;
        $items=$items->fetchAll();
        
        $objects=0;
        $map->items=Array();
        foreach ($items as $item) {
            $objects++;
            if ($objects>$opts->maxmapobjects) continue;
            $props=New \StdClass();
            $props->itemid=$item->itemid;
            $props->cv=$item->cv;
            $props->loi=$item->loi;
            $props->stddev=$item->stddev_;
            $props->min=$item->min_;
            $props->max=$item->max_;
            $props->avg=$item->avg_;
            $props->cnt=$item->cnt;            
            $props->gurl1=sprintf("%s/chart.php?itemids[]=%d&period=%d&stime=%d&width=200&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            $props->gurl2=sprintf("%s/chart.php?itemids[]=%d&period=%d&stime=%d&width=1025&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            if ($opts->outputverb=="expanded") {
                $props->description= HsPresenter::expandHost($item->hostid).":".IsPresenter::expandItem($item->itemid);
                $map->items[$item->itemid]=$props->description;
            } else {
                $props->description=$item->itemid;
                $map->items[$item->itemid]=$item->itemid;
            }
            //$props->height=round(50*($item->loi/$maxloi));
            $props->width=100;
            $props->class=Array();
            #$props->class[]="loi".self::numtostep($item->loi,$minloi,$maxloi,10);
            #$props->class[]="cv".self::numtostep($item->cv,$mincv,$maxcv,10);
            #$props->class[]="stddev".self::numtostep($item->stddev_,$minstddev,$maxstddev,10);
            #$props->class[]="cnt".self::numtostep($item->cnt,$mincnt,$maxcnt,10);
           
            $child=New Node($item->itemid);
            $child->setValue($props);
            $map->addChild($child);
        }
        $this->template->map=$map;
        $i=1;
        $zitemids="";
        $overallitems=Array();
        $overallitemscnt=20;
        foreach ($items as $item) {
            $zitemids.="itemids[]=$item->itemid&";
            $overallitems[]=$item->itemid;
            $i++;
            if ($i>$overallitemscnt) break;
        }
        $this->template->overallitems=$overallitems;
        $overallitemsjs=join(",",$overallitems);
        $overalllabels=Array("clock");
        foreach ($overallitems as $i) {
            $overalllabels[$i]=$map->items[$i];
        }
        
        $this->template->overalldygraph=(object) Array(
            "id" => "overall",
            "legendid" => "overall_l",
            "title" => "Overall graph",
            "xlabel" => sprintf("Time (from %s UTC, length %s seconds)",$w->tfrom,$w->seconds),
            "ylabel" => "Normalized value",
            "acolors" => Array( "#001100", "#002200","#003300","#004400","#005500","#006600","#007700","#008800","#009900","#00aa00" ),
            "labels" => $overalllabels,
            "width" => 800,
            "csv" => "$opts->zaburl/monda/is/zabbixhistory?w=$w->id&Ii=$overallitemsjs&csv_enclosure=&csv_delimiter=,"
        );
        $this->template->title=$w->description;
        $this->template->top10graph=sprintf("%s/chart.php?%s&&graphtype=0&period=%d&stime=%d&width=1025&curtime=%d",$opts->zaburl,$zitemids,$w->seconds,$w->fstamp,time());
    }

}
