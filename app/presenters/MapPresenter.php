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
                "loi_sizefactor",
                false,"loi_sizefactor",
                "Size factor for loi",
                2,
                2
                );
        $ret=self::parseOpt($ret,
                "loi_minsize",
                false,"loi_minsize",
                "Minimum box size",
                0.1,
                0.1
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
        if ($range==0 || $max==0) {
            return(1);
        }
        $step=$range/$steps;
        $ret=min(1+round($steps*($num/$max)),$steps);
        return($ret);
    }
    
    function addclass($props,$class) {
        $props->class[]=$class;
        $props->$class=1;
        return($props);
    }
    
    function isclass($props,$class) {
        return(isset($props->$class));
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
        $map->w=$w;
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
            $props->gurl1=sprintf("%s/chart.php?itemids[]=%d&period=%d&stime=%d&width=200&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            $props->gurl2=sprintf("%s/chart.php?itemids[]=%d&period=%d&stime=%d&width=1025&curtime=%d",$opts->zaburl,$item->itemid,$w->seconds,$w->fstamp,time());
            if ($opts->outputverb=="expanded") {
                $props->description= HsPresenter::expandHost($item->hostid).":".IsPresenter::expandItem($item->itemid);
            } else {
                $props->description=$item->itemid;
            }
            
            $props->height=round(50*($item->loi/$maxloi));
            $props->width=100;
            $props->class=Array();
            $props=self::addclass($props,"loi".self::numtostep($item->loi,$minloi,$maxloi,10));
            $props=self::addclass($props,"cv".self::numtostep($item->cv,$mincv,$maxcv,10));
            $props=self::addclass($props,"stddev".self::numtostep($item->stddev_,$minstddev,$maxstddev,10));
            $props=self::addclass($props,"cnt".self::numtostep($item->cnt,$mincnt,$maxcnt,10));
           
            $child=New Node($item->itemid);
            $child->setValue($props);
            $map->addChild($child);
        }
        $this->template->map=$map;
        $i=1;
        $zitemids="";
        foreach ($items as $item) {
            $zitemids.="itemids[]=$item->itemid&";
            $i++;
            if ($i>10) break;
        }
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
                $twids[$id]=  \App\Model\Tw::twGet($id);
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
                $props->loih=$window->loih;
                $props->size=$window->loi*$this->opts->loi_sizefactor+$this->opts->loi_minsize;
                $opts2=$this->opts;
                $opts2->wid=$window->id;
                if ($this->opts->itemids) {
                    $itemids=$this->opts->itemids;
                } else {
                    $opts=$this->opts;
                    $opts->wids=Array($id);
                    $itemids=  \App\Model\ItemStat::isToIds($opts);
                }
                $props->url=self::ZabbixGraphUrl1($itemids,$window->fstamp,$window->seconds);
                $props->description=$window->description;
                $props->zabbix=$window->description;
                $props->class=Array();
                $props=self::addclass($props,"loi".self::numtostep($window->loi,$stats["minloi"],$stats["maxloi"],10));
                $props=self::addclass($props,"loih".self::numtostep($window->loih,$stats["minloih"],$stats["maxloih"],10));
                $props=self::addclass($props,"processed".self::numtostep($window->processed,$stats["minprocessed"],$stats["maxprocessed"],10));
                $props=self::addclass($props,"ignored".self::numtostep($window->ignored,$stats["minignored"],$stats["maxignored"],10));
                switch ($window->seconds) {
                    case \App\Model\Monda::_1HOUR:
                        $props=self::addclass($props,"l_hour");
                        $props=self::addclass($props,"hour_".date("H",$props->fstamp+date("Z")));
                        break;
                    case \App\Model\Monda::_1DAY:
                        $props=self::addclass($props,"l_day");
                        $props=self::addclass($props,"dow_".date("l",$props->fstamp+date("Z")));
                        if (date("l",$props->fstamp+date("Z"))==$this->opts->sow) {
                            $props=self::addclass($props,"day_sow");
                        }
                        break;
                    case \App\Model\Monda::_1WEEK:
                        $props=self::addclass($props,"l_week");
                        //$props->size=3;
                        break;
                    case \App\Model\Monda::_1MONTH:
                        $props=self::addclass($props,"l_month");
                        $props=self::addclass($props,"month_".date("M",$props->fstamp+date("Z")));
                        //$props->size=4;
                        break;
                   case \App\Model\Monda::_1YEAR:
                        $props=self::addclass($props,"l_year");
                        //$props->size=5;
                        break;
                }
                if ($window->processed==0) {
                    $props=self::addclass($props,"processed0");
                }
                if ($window->found==0) {
                    $props=self::addclass($props,"found0");
                }
            } else {
                $props->id=$id;
                $props->loi=0;
            }
            $map=New Node($props->id);
            $map->setValue($props);
            return($map);
        }
    }
    
    function renderHs() {
        $map = New Node("monda");
        $hosts = \App\Model\HostStat::hsSearch($this->opts)->fetchAll();
        $windows = \App\Model\Tw::twSearch($this->opts)->fetchAll();
        $stats = \App\Model\Tw::twStats($this->opts);
        foreach ($windows as $window) {
            if (!$window->loi)
                continue;
            $twnode = New Node($window->id);
            $props = New \StdClass();
            $props->id = $window->id;
            $props->name = $window->description;
            $props->loi = $window->loi;
            $props->size = $window->loi*$this->opts->loi_sizefactor+$this->opts->loi_minsize;
            $props->url = "";
            $twnode->setValue($props);
            foreach ($hosts as $host) {
                if ($host->windowid != $window->id)
                    continue;
                $hostnode = New Node($window->id . $host->hostid);
                $props = New \StdClass();
                $props->id = $window->id . $host->hostid;
                $props->name = HsPresenter::expandHost($host->hostid);
                $props->loi = $host->loi;
                $props->size = $host->loi*$this->opts->loi_sizefactor+$this->opts->loi_minsize;
                $props->url = "";
                $hostnode->setValue($props);
                $twnode->addChild($hostnode);
            }
            $map->addChild($twnode);
        }
        $props = New \StdClass();
        $props->id = "monda";
        $map->setValue($props);
        $this->template->map = $map;
    }

}