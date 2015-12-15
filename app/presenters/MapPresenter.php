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
                "minmapdepth",
                false,"minmapdepth",
                "Minimum depth of map",
                false,
                "No limit"
                );
        $ret=self::parseOpt($ret,
                "maxmapdepth",
                false,"maxmapdepth",
                "Maximum depth of map",
                false,
                "No limit"
                );
        $ret=self::parseOpt($ret,
                "maxmapobjects",
                false,"maxmapobjects",
                "Maximum number of objects in map",
                40,
                40
                );
        return($ret);
    }
    
    public function createMap($name,$props=false) {
        $map=New Node($name);
        if (!$props) {
            $props=New \stdClass();
        }
        $props->name=$name;
        $map->setValue($props);
        return($map);
    }
    
    function numtostep($num,$min,$max,$steps=10) {
        $range=abs($max-$min);
        if ($range==0 || $max==0) {
            return(1);
        }
        $step=$range/$steps;
        $ret=min(1+round($steps*($num/$max)),$steps);
        return($ret);
    }
    
    function renderTw() {
        $opts=$this->opts;
        if (!$opts->wids || count($opts->wids)>1) {
            self::mexit(2,"Bad window ids!\n");
        }
        $map=self::createMap($opts->mapname);
        $items= \App\Model\ItemStat::IsSearch($opts);
        if (!$items) {
            self::mexit(2,"No items!\n");
        }
        $w=\App\Model\Tw::twGet($opts->wids);
        $map->w=$w;
        $this->template->wid=$w->id;
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
        $objects=0;
        $map->items=Array();
        foreach ($items as $item) {
            $objects++;
            if ($objects>$opts->maxmapobjects) continue;
            $props=New \StdClass();
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
                $map->items[]=$props->description;
            } else {
                $props->description=$item->itemid;
                $map->items[]=$item->itemid;
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
        $i=1;
        $zitemids="";
        $t10i=Array();
        foreach ($items as $item) {
            $zitemids.="itemids[]=$item->itemid&";
            $t10i[]=$item->itemid;
            $i++;
            if ($i>10) break;
        }
        $this->template->top10items=$t10i;
        $top10itemsjs=join(",",$t10i);
        $this->template->top10csv="$opts->zaburl/monda/is/zabbixhistory?w=$w->id&Ii=$top10itemsjs&csv_enclosure=&csv_delimiter=,";
        $this->template->top10graph=sprintf("%s/chart.php?%s&&graphtype=0&period=%d&stime=%d&width=1025&curtime=%d",$opts->zaburl,$zitemids,$w->seconds,$w->fstamp,time());
    }
    
    function TwTreeMap($tree,$twids,$stats,$zstats,$id=false) {
        if (is_array($tree)) {
            $map=self::TwTreeMap($id,$twids,$stats,$zstats,$id);
            foreach ($tree as $wid=>$subtree) {
                $submap=self::TwTreeMap($subtree,$twids,$stats,$zstats,$wid);
                $map->addChild($submap);
            }
            return($map);
        } else {
            $props=New \StdClass();
            if (is_int($id) && !array_key_exists($id,$twids)) {
                $twids[$id]= \App\Model\Tw::twGet($id);
            }
            if (array_key_exists($id,$twids)) {
                $window=$twids[$id];
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
                $props->zabbix=$window->description;
                $props->class=Array();
                $props->class[]="loi".self::numtostep($window->loi,$stats["minloi"],$stats["maxloi"],10);
                $props->class[]="loih".self::numtostep($window->loih,$stats["minloih"],$stats["maxloih"],10);
                $props->class[]="processed".self::numtostep($window->processed,$stats["minprocessed"],$stats["maxprocessed"],10);
                $props->class[]="ignored".self::numtostep($window->ignored,$stats["minignored"],$stats["maxignored"],10);
                switch ($window->seconds) {
                    case \App\Model\Monda::_1HOUR:
                        $props->class[]="l_hour";
                        $props->class[]="hour_".date("H",$props->fstamp+date("Z"));
                        break;
                    case \App\Model\Monda::_1DAY:
                        $props->class[]="l_day";
                        $props->class[]="dow_".date("l",$props->fstamp+date("Z"));
                        if (date("l",$props->fstamp+date("Z"))==$this->opts->sow) {
                            $props->class[]="day_sow";
                        }
                        break;
                    case \App\Model\Monda::_1WEEK:
                        $props->class[]="l_week";
                        break;
                    case \App\Model\Monda::_1MONTH:
                        $props->class[]="l_month";
                        $props->class[]="month_".date("M",$props->fstamp+date("Z"));
                        break;
                   case \App\Model\Monda::_1YEAR:
                        $props->class[]="l_year";
                        break;
                }
                if ($window->processed==0) {
                    $props->class[]="processed0";
                }
                if ($window->found==0) {
                    $props->class[]="found0";
                }
            } else {
                $props->id=$id;
            }
            $map=New Node($props->id);
            $map->setValue($props);
            return($map);
        }
    }
    
    function renderTl() {
        $opts=$this->opts;
        $opts->wsort="start/+";
        $wids=\App\Model\Tw::twToIds($opts);
        $tree=\App\Model\Tw::twTree($wids,$opts->minmapdepth,$opts->maxmapdepth);
        $stats=  \App\Model\Tw::twStats($opts);
        $twids=Array();
        foreach ($wids as $w) {
            if (!array_key_exists($w,$twids)) {
                $twids[$w]=\App\Model\Tw::twGet($w);
            }
        }
        $map=self::TwTreeMap($tree,$twids,$stats,false,$opts->mapname);
        $this->template->map=$map;
    }
    
    function renderMonth() {
        $opts=$this->opts;
        $opts->wsort="start/+";
        $opts->maxmapdepth=3;
        $opts->length=Array(\App\Model\Monda::_1DAY,\App\Model\Monda::_1WEEK,\App\Model\Monda::_1MONTH);
        $wids=\App\Model\Tw::twToIds($opts);
        $tree=\App\Model\Tw::twTree($wids,$opts->minmapdepth,$opts->maxmapdepth);
        $wstats=  \App\Model\Tw::twStats($opts);
        $this->template->map=$tree;
    }
    
}