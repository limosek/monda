<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
    Nette\Utils\DateTime as DateTime,
    Tree\Node\Node;

class MapPresenter extends BasePresenter
{
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=IsPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "maptype",
                "Mt","maptype",
                "Selector for map type to create",
                "html",
                "html",
                Array("html")
                );
        $ret=self::parseOpt($ret,
                "mapname",
                "Mn","mapname",
                "Map name to create",
                "monda",
                "monda"
                );
        $ret=self::parseOpt($ret,
                "zaburl",
                "Zu","zabbix_url",
                "Base of zabbix urls",
                "http://localhost/zabbix",
                "http://localhost/zabbix"
                );
        return($ret);
    }
    
    public function createMap($name,$props=false) {
        $map=New Node($name);
        $props->name=$name;
        $map->setValue($props);
        return($map);
    }
    
    function numtostep($num,$min,$max,$steps=10) {
        $range=abs($max-$min);
        $step=$range/$steps;
        $ret=min(1+round($steps*($num/$max)),$steps);
        return($ret);
    }
    
    function renderTw() {
        $opts=$this->opts;
        if (!$opts->wids || count($wids)>1) {
            self::mexit(2,"Bad window ids!\n");
        }
        $map=self::createMap($opts->mapname);
        $items= \App\Model\ItemStat::IsSearch($opts);
        if (!$items) {
            self::mexit(2,"No items!\n");
        }
        $w=\App\Model\Tw::twGet($opts->wids);
        $items=$items->fetchAll();
        $maxcv=0;
        $maxloi=0;
        $maxstddev=0;
        $mincv=0;
        $minloi=0;
        $minstddev=0;
        $mincnt=0;
        $maxcnt=0;
        foreach ($items as $item) {
            $maxcv=max($maxcv,$item->cv);
            $mincv=min($mincv,$item->cv);
            $minstddev=min($minstddev,$item->stddev_);
            $maxstddev=max($maxstddev,$item->stddev_);
            $minloi=min($minloi,$item->loi);
            $maxloi=max($maxloi,$item->loi);
            $mincnt=min($mincnt,$item->cnt);
            $maxcnt=max($maxcnt,$item->cnt);
        }
        foreach ($items as $item) {
            $props=New \StdClass();
            $props->cv=$item->cv;
            $props->loi=$item->loi;
            $props->stddev=$item->stddev_;
            $props->min=$item->min_;
            $props->max=$item->max_;
            $props->avg=$item->avg_;
            $props->cnt=$item->cnt;
            $props->gurl1=sprintf("%s/chart.php?itemid=%d&period=%d&stime=%d&width=200&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            $props->gurl2=sprintf("%s/chart.php?itemid=%d&period=%d&stime=%d&width=1025&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            if ($opts->outputverb=="expanded") {
                $props->description= HsPresenter::expandHost($item->hostid).":".IsPresenter::expandItem($item->itemid);
            } else {
                $props->description=$item->itemid;
            }
            
            $props->height=round(50*($item->loi/$maxloi));
            $props->width=100;
            $props->class=Array();
            $props->class[]="loi".self::numtostep($item->loi,$minloi,$maxloi,10);
            $props->class[]="cv".self::numtostep($item->cv,$mincv,$maxcv,10);
            $props->class[]="stddev".self::numtostep($item->stddev_,$minstddev,$maxstddev,10);
            $props->class[]="cnt".self::numtostep($item->cnt,$mincnt,$maxcnt,10);
           
            $child=New Node($item->itemid);
            $child->setValue($props);
            $map->addChild($child);
        }
        $this->template->map=$map;
    }
    
    function TwTreeMap($tree,$twids,$stats,$id=false) {
        if (is_array($tree)) {
            $map=self::TwTreeMap($id,$twids,$stats,$id);
            foreach ($tree as $wid=>$subtree) {
                $submap=self::TwTreeMap($subtree,$twids,$stats,$wid);
                $map->addChild($submap);
            }
            return($map);
        } else {
            $props=New \StdClass();
            if (is_int($id) && !array_key_exists($id,$twids)) {
                $twids[$id]= \App\Model\Tw::twGet($id);
            }
            $window=$twids[$id];
            $props->id=$window->id;
            $props->id=$window->id;
            $props->cv=$window->loi;
            $props->seconds=$window->seconds;
            $props->processed=$window->processed;
            $props->found=$window->found;
            $props->fstamp=$window->fstamp;
            $props->tstamp=$window->tstamp;
            $props->loi=$window->loi;
            $props->url=self::link("Tw",Array("w"=>$window->id));
            $props->description=$window->description;
            $props->class=Array();
            $props->class[]="loi".self::numtostep($window->loi,$stats["minloi"],$stats["maxloi"],10);
            $props->class[]="processed".self::numtostep($window->processed,$stats["minprocessed"],$stats["maxprocessed"],10);
            $props->class[]="ignored".self::numtostep($window->ignored,$stats["minignored"],$stats["maxignored"],10);
            $props->class[]="dow_".date("l",$props->fstamp+date("Z"));
            $props->class[]="hour_".date("H",$props->fstamp+date("Z"));
            $props->class[]="month_".date("M",$props->fstamp+date("Z"));
            $map=New Node($props->id);
            $map->setValue($props);
            return($map);
        }
    }
    
    function renderTl() {
        $opts=$this->opts;
        $opts->wsort="start/+";
        $wids=\App\Model\Tw::twToIds($opts);
        $tree=\App\Model\Tw::twTree($wids);
        $maxloi=0;
        $minloi=0;
        foreach ($wids as $w) {
            if (!array_key_exists($w,$twids)) {
                $twids[$w]=\App\Model\Tw::twGet($w);
            }
            $maxloi=max($maxloi,$twids[$w]->loi);
            $minloi=min($minloi,$twids[$w]->loi);
            $maxprocessed=max($maxprocessed,$twids[$w]->processed);
            $minprocessed=min($minprocessed,$twids[$w]->processed);
            $maxignored=max($maxignored,$twids[$w]->ignored);
            $minignored=min($minignored,$twids[$w]->ignored);
        }
        $stats=Array(
            "minloi" => $minloi,
            "maxloi" => $maxloi,
            "minignored" => $minignored,
            "maxignored" => $maxignored,
            "minprocessed" => $minprocessed,
            "maxprocessed" => $maxprocessed
        );
        $map=self::TwTreeMap($tree,$twids,$stats,$opts->mapname);
        $this->template->map=$map;
    }
    
}