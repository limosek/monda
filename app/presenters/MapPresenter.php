<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\HostStat,
    App\Model\ItemCorr,
    App\Model\EventCorr,
    App\Model\Monda,
    App\Model\Util,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Exception,
    Nette\Templating\FileTemplate,
    Nette\Utils\DateTime as DateTime,
    Tree\Node\Node;

class MapPresenter extends BasePresenter {

    function startup() {
        parent::startup();
        TwPresenter::startup();
        HsPresenter::startup();
        IsPresenter::startup();
        IcPresenter::startup();
        EcPresenter::startup();

        Opts::addOpt(false, "tw_graph_items", "How many items to place in url of graphs", 10, 10
        );
        Opts::addOpt(false, "loi_sizefactor", "Size factor for loi", 2, 2
        );
        Opts::addOpt(false, "loi_minsize", "Minimum box size", 0.1, 0.1
        );
        Opts::setDefaults();
        Opts::readCfg(Array( "Tw", "Is", "Hs", "Ic", "Ec", "Map"));
        Opts::readOpts($this->params);
        self::postCfg();
    }
    
    static function postCfg() {
        parent::postCfg();
        TwPresenter::postCfg();
        IsPresenter::postCfg();
        IcPresenter::postCfg();
        EcPresenter::postCfg();
    }

    static function createMap($name, $props = false) {
        $map = New Node($name);
        $props->name = $name;
        $map->setValue($props);
        return($map);
    }

    function renderTl() {
        $tl=Tw::twSearch()->fetchAll();
        $stats=Tw::twStats();
        $this->template->title=sprintf("Monda Timeline from %s to %s",Util::datetime(Opts::getOpt("start")),Util::datetime(Opts::getOpt("end")));
        foreach ($tl as $tw) {
            $tw->class = Array();
            $tw = Util::addclass($tw, "loi" . Util::numtostep($tw->loi, $stats["minloi"], $stats["maxloi"], 10));
            $tw = Util::addclass($tw, "loih" . Util::numtostep($tw->loih, $stats["minloih"], $stats["maxloih"], 10));
            $tw = Util::addclass($tw, "processed" . Util::numtostep($tw->processed, $stats["minprocessed"], $stats["maxprocessed"], 10));
            $tw = Util::addclass($tw, "ignored" . Util::numtostep($tw->ignored, $stats["minignored"], $stats["maxignored"], 10));
            $tw->url1=Util::zabbixGraphUrl1(Opts::GetOpt("itemids"), $tw->fstamp, $tw->seconds);
        }
        $this->template->tl=$tl;
    }

    function renderTw() {
        if (Opts::isDefault("max_rows")) {
            Opts::setOpt("max_rows",20);
        }
        if (count(Opts::getOpt("window_ids"))==0) {
            throw New Exception("No windows to create report. Use -w.");
        }
        if (count(Opts::getOpt("window_ids"))>1) {
            throw New Exception("Tw report needs only one window.");
        }
        $items = ItemStat::IsSearch()->fetchAll();
        if (count($items) == 0) {
            throw New Exception("No items to create report.");
        }
        $w = Tw::twGet(Opts::getOpt("window_ids"));
        foreach ($items as $c=>$i) {
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i->key=IsPresenter::expandItem($i->itemid,true);
                $i->url1=Util::zabbixGraphUrl1(Array($i->itemid), $w->fstamp, $w->seconds);
                $i->url2=Util::zabbixGraphUrl2(Array($i->itemid), $w->fstamp, $w->seconds);
            } else {
                $i->key=$i->itemid;
                $i->url1="";
                $i->url2="";
            }
        }
        $hosts = HostStat::HsSearch()->fetchAll();
        foreach ($hosts as $c=>$h) {
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $h->host=HsPresenter::expandHost($h->hostid);
            } else {
                $h->host=$h->hostid;
            }
        }
        
        $this->template->items = $items;
        $this->template->hosts = $hosts;
        $this->template->w = $w;
        $this->template->title=sprintf("Monda Timeline for window id %s",$w[0]);
    }
    
    function renderHs() {
        if (Opts::isDefault("max_rows")) {
            Opts::setOpt("max_rows",20);
        }
        if (count(Opts::getOpt("hostids"))==0) {
            throw New Exception("No hosts to create report. Use --hostids.");
        }
        if (count(Opts::getOpt("hostids"))>1) {
            throw New Exception("Host report needs only one host.");
        }
        $hostids=Opts::getOpt("hostids");
        $items = ItemStat::IsSearch()->fetchAll();
        if (count($items) == 0) {
            throw New Exception("No items to create report.");
        }
        $istats = ItemStat::IsStats();
        $wstats = Tw::twStats();
        $windows = Tw::twSearch()->fetchAll();
        foreach ($windows as $w) {
            Opts::setOpt("window_ids",Array($w->id));
            $is=ItemStat::IsSearch()->fetchAll();
            foreach ($is as $c=>$i) {
                if (Opts::getOpt("output_verbosity") == "expanded") {
                    $i->key=IsPresenter::expandItem($i->itemid,true);
                    $i->url1=Util::zabbixGraphUrl1(Array($i->itemid), $w->fstamp, $w->seconds);
                    $i->url2=Util::zabbixGraphUrl2(Array($i->itemid), $w->fstamp, $w->seconds);
                } else {
                    $i->key=$i->itemid;
                    $i->url1="";
                    $i->url2="";
                }
                $i->class = Array();
                $i = Util::addclass($i, "loi" . Util::numtostep($i->loi, $istats->minloi, $istats->maxloi, 10));
            }
            $w->is=$is;
            $w->class = Array();
            $w = Util::addclass($w, "loi" . Util::numtostep($w->loi, $wstats->minloi, $wstats->maxloi, 10));
        }
        $this->template->windows = $windows;
        $this->template->title=sprintf("Monda Host satus for %s",  HsPresenter::expandHost($hostids[0]));
    }

    function TwTreeMap($tree, $twids, $stats, $id = false) {
        if (is_array($tree)) {
            $map = self::TwTreeMap($id, $twids, $stats, $id);
            foreach ($tree as $wid => $subtree) {
                $submap = self::TwTreeMap($subtree, $twids, $stats, $wid);
                $map->addChild($submap);
            }
            return($map);
        } else {
            $props = New \StdClass();
            if (is_int($id) && !array_key_exists($id, $twids)) {
                $twids[$id] = Tw::twGet($id);
            }
            if (array_key_exists($id, $twids)) {
                $window = $twids[$id];
                $props->id = $window->id;
                $props->cv = $window->loi;
                $props->seconds = $window->seconds;
                $props->processed = $window->processed;
                $props->found = $window->found;
                $props->fstamp = $window->fstamp;
                $props->tstamp = $window->tstamp;
                $props->loi = $window->loi;
                $props->loih = $window->loih;
                $props->size = $window->loi * Opts::getOpt("loi_sizefactor") + Opts::getOpt("loi_minsize");
                if (count(Opts::getOpt("itemids"))>0) {
                    $itemids = Opts::getOpt("itemids");
                } else {
                    $itemids = ItemStat::isToIds();
                }
                $props->url = Util::ZabbixGraphUrl1($itemids, $window->fstamp, $window->seconds);
                $props->description = $window->description;
                $props->zabbix = $window->description;
                $props->class = Array();
                $props = Util::addclass($props, "loi" . Util::numtostep($window->loi, $stats["minloi"], $stats["maxloi"], 10));
                $props = Util::addclass($props, "loih" . Util::numtostep($window->loih, $stats["minloih"], $stats["maxloih"], 10));
                $props = Util::addclass($props, "processed" . Util::numtostep($window->processed, $stats["minprocessed"], $stats["maxprocessed"], 10));
                $props = Util::addclass($props, "ignored" . Util::numtostep($window->ignored, $stats["minignored"], $stats["maxignored"], 10));
                switch ($window->seconds) {
                    case Monda::_1HOUR:
                        $props = Util::addclass($props, "l_hour");
                        $props = Util::addclass($props, "hour_" . date("H", $props->fstamp + date("Z")));
                        break;
                    case Monda::_1DAY:
                        $props = Util::addclass($props, "l_day");
                        $props = Util::addclass($props, "dow_" . date("l", $props->fstamp + date("Z")));
                        if (date("l", $props->fstamp + date("Z")) == Opts::getOpt("sow")) {
                            $props = Util::addclass($props, "day_sow");
                        }
                        break;
                    case Monda::_1WEEK:
                        $props = Util::addclass($props, "l_week");
                        break;
                    case Monda::_1MONTH:
                        $props = Util::addclass($props, "l_month");
                        $props = Util::addclass($props, "month_" . date("M", $props->fstamp + date("Z")));
                        break;
                    case Monda::_1YEAR:
                        $props = Util::addclass($props, "l_year");
                        break;
                }
                if ($window->processed == 0) {
                    $props = Util::addclass($props, "processed0");
                }
                if ($window->found == 0) {
                    $props = Util::addclass($props, "found0");
                }
            } else {
                $props->id = $id;
                $props->loi = 0;
            }
            $map = New Node($props->id);
            $map->setValue($props);
            return($map);
        }
    }

}
