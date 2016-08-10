<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\ItemCorr,
    App\Model\Monda, 
    App\Model\TriggerInfo, 
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Nette\Utils\DateTime as DateTime;

class IcPresenter extends BasePresenter {

    public function Help() {
        CliDebug::warn("

ItemCorr operations
            
     ic:show [-w wid] [--items item1[,item2]] [common opts]
        Show computed item correlations

     ic:stats [commom opts]
        Show item correlation statistics

     ic:matrix [common opts]
        Show correlation matrix
        
     ic:history [-w wid] [--triggerids id1[,id2]] --output_mode {csv|arff} [common opts]
        Show correlation history

     ic:thistory  [-w wid] --triggerids id1[,id2] [common opts]
        Show correlations based on triggerids and their items

     ic:compute [common opts]
        Compute correlations
        
     ic:delete [common opts]
        Delete computed correlations

     ic:loi [common opts]
        Update correlations LOI
 
     [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        IsPresenter::startup();
        
        Opts::addOpt(
                false, "corr_type", "Selector for windows to correlate with basic windows", "samewindow", "samewindow", Array("samewindow", "samehour", "samedow")
        );
        Opts::addOpt(
                false, "ic_minloi", "Select only item correlation which have loi bigger than this/=.", 0, 0
        );
        Opts::addOpt(
                false, "ic_notsame", "Report only correlations with other items, not itself (corr<>1)", 1, 1
        );
        Opts::addOpt(
                false, "ic_notsamehost", "Report only correlations between hosts", 0, 0
        );
        Opts::addOpt(
                false, "ic_sort", "Sort correlation (to compute or to show) by {start|id|loi}", "loi/-", "loi/-"
        );
        Opts::addOpt(
                false, "ic_all", "Force to compute all combinations", false, "no"
        );
        Opts::addOpt(
                false, "time_precision", "Time precision (maximum difference in time for correlation) in seconds", 5, 5
        );
        Opts::addOpt(
                false, "min_values_for_corr", "Minimum values to make correlation", 40, 40
        );
        Opts::addOpt(
                false, "max_values_for_corr", "Maximum values to make correlation", 5000, 5000
        );
        Opts::addOpt(
                false, "ic_max_items_at_once", "Maximum itemids for one query to correlate", 20, 20
        );
        Opts::addOpt(
                false, "min_corr", "Minimum correlation to report (bigger than)", 0.4, 0.4
        );
        Opts::addOpt(
                false, "max_corr", "Maximum correlation to report (less than)", 1, 1
        );
        Opts::addOpt(
                false, "ic_history_interval", "When getting history of event, get this seconds of history arround.", 3600, "1hour"
        );
        Opts::addOpt(
                false, "ic_cache_expire", "How long to cache ic results in seconds.", 300, 300
        );
        
        Opts::setDefaults();
        Opts::readCfg(Array("Ic"));
        Opts::readOpts($this->params);
        self::postCfg();
        if ($this->action=="stats") {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("itemid1","itemid2","wcnt1","wcnt2","acorr"));
            }
        } else {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("windowid1","itemid1","windowid2","itemid2","corr","icloi"));
            }
        }
    }

    public static function postCfg() {
        IsPresenter::postCfg();
        switch (Opts::getOpt("corr_type")) {
            case "samehour":
                Opts::setOpt("window_length", Array(Monda::_1HOUR));
                break;
            case "samedow":
                Opts::setOpt("window_length", Array(Monda::_1DAY));
                break;
        }
    }

    public function renderShow() {
        $rows = ItemCorr::icSearch()->fetchAll();
        if ($rows) {
            $this->exportdata = $rows;
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i = 0;
                foreach ($this->exportdata as $i => $row) {
                    $i++;
                    CliDebug::dbg(sprintf("Processing %d row of %d                 \r", $i, count($this->exportdata)));
                    $row["key1"] = IsPresenter::expandItem($row->itemid1, true);
                    $row["key2"] = IsPresenter::expandItem($row->itemid2, true);
                    $row["window1"] = TwPresenter::expandTw($row->windowid1);
                    $row["window2"] = TwPresenter::expandTw($row->windowid2);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        } else {
            self::helpEmpty();
        }
        self::mexit();
    }

    public function renderHistory() {
        if (Opts::getOpt("output_mode") == "brief") {
            self::mexit(3, "This action is possible only with csv output mode.\n");
        }
        Opts::setOpt("ic_sort", "start/+");
        Opts::setOpt("ic_notsame", true);
        if (!Opts::getOpt("itemids")) {
            self::mexit("You must select items!\n");
        }
        if (count(Opts::getOpt("window_length")) > 1) {
            self::mexit(3, "You must select same window length (-l).\n");
        }
        $itemids = Opts::getOpt("itemids");
        $tws = Tw::twToIds();
        foreach ($tws as $tw) {
            $w = Tw::twGet($tw);
            ItemCorr::IcCompute($w->id, $w->id, $itemids, $w->fstamp, $w->tstamp, $w->fstamp, $w->tstamp, Opts::getOpt("time_precision"), Opts::getOpt("min_values_for_corr"), Opts::getOpt("max_values_for_corr"), false);
            $this->exportdata[$tw] = ItemCorr::TwCorrelationsByItemid($tw, $itemids);
            $this->exportdata[$tw]["windowid"] = $tw;
        }
        foreach (array_keys($this->exportdata[$tw]) as $itempair) {
            List($item1, $item2) = preg_split("/-/", $itempair);
            if ($item2) {
                $this->exportinfo[$item1 . "-" . $item2] = IsPresenter::expandItem($item1) . "__" . IsPresenter::expandItem($item2);
                $this->arffinfo[$item1 . "-" . $item2] = "NUMERIC";
            }
        }
        $this->exportinfo["windowid"] = "windowid";
        $this->arffinfo["windowid"] = "numeric";
        parent::renderShow($this->exportdata);
        self::mexit();
    }

    public function renderTHistory() {
        if (Opts::getOpt("output_mode") == "brief") {
            self::mexit(3, "This action is possible only with csv or arff output mode.\n");
        }
        if (count(Opts::getOpt("window_length"))>1) {
            self::mexit(3, "You must select same window length (-l).\n");
        }
        Opts::setOpt("ic_sort", "start/+");
        
        $hosts = Array();
        foreach ($items as $item) {
            $ii = ItemStat::itemInfo($item);
            $hosts[$ii[0]->hostid] = $ii[0]->hostid;
        }
        CliDebug::info(sprintf("Trigger items: %s, hosts: %s\n", join(",", $items), join(",", $hosts)));
        Opts::setOpt("tw_sort","start/+");
        $tws = Tw::twSearch()->fetchAll();
        $events = TriggerInfo::Triggers2Events(Opts::getOpt("start") - Opts::getOpt("events_prefetch"), Opts::getOpt("end"), Opts::getOpt("triggerids"));
        $valuesok=0;
        $valuesproblem=0;
        foreach ($tws as $tw) {
            $wid = $tw->id;
            List($event,$value)=TriggerInfo::eventValueForInterval($events, $tw->fstamp, $tw->tstamp, false);
            CliDebug::info(sprintf("Timewindow %d, %s, %f\n",$wid,$tw->tfrom,$value));
            if ($value>Opts::GetOpt("wevent_problem_treshold")) {
                $value="PROBLEM";
            } else {
                $value="OK";
            }
            if (Opts::getOpt("event_value_filter")) {
                if (Opts::getOpt("event_value_filter")=="50") {
                    if ($valuesproblem>$valuesok && $value=="OK") {
                        $valuesok++;
                    } elseif ($value=="PROBLEM") {
                        $valuesproblem++;
                    } else {
                        CliDebug::info(sprintf("Skipping timewindow %d due to 50/50 filter\n",$wid));
                        continue;
                    }
                } elseif ($value!=Opts::getOpt("event_value_filter")) {
                    continue;
                }
            }
            $this->exportdata[$wid]["windowid"] = $wid;
            $corrs = ItemCorr::IcCompute($wid, $wid, $items, $tw->fstamp, $tw->tstamp, $tw->fstamp, $tw->tstamp, Opts::getOpt("time_precision"), Opts::getOpt("min_values_for_corr"), Opts::getOpt("max_values_for_corr"), true, true);
            foreach ($corrs as $corr) {
                foreach ($items as $itemid1) {
                    foreach ($items as $itemid2) {
                        if ($itemid1 < $itemid2) {
                            if ($corr["itemid1"] == $itemid1 && $corr["itemid2"] == $itemid2) {
                                $this->exportdata[$wid]["$itemid1" . "-" . "$itemid2"] = $corr["corr"];
                            } else {
                                $this->exportdata[$wid]["$itemid1" . "-" . "$itemid2"] = 0;
                            }
                        }
                    }
                }
            }
            $tid = $event->relatedObject->triggerid;
            $this->exportdata[$wid][$tid]=$value;
            $this->exportinfo[$tid] = TriggerInfo::expandTrigger($tid);
            $this->arffinfo[$tid] = "{OK,PROBLEM}";
            $this->exportinfo["windowid"] = "windowid";
            $this->arffinfo["windowid"] = "NUMERIC";
            foreach (array_keys($this->exportdata[$wid]) as $itempair) {
                List($item1, $item2) = preg_split("/-/", $itempair);
                if ($item2) {
                    $this->exportinfo[$item1 . "-" . $item2] = IsPresenter::expandItem($item1) . "__" . IsPresenter::expandItem($item2);
                    $this->arffinfo[$item1 . "-" . $item2] = "NUMERIC";
                }
            }
        }
        parent::renderShow($this->exportdata);
        self::mexit();
    }

    public function renderMatrix() {
        $rows = ItemCorr::icSearch();
        $m = Array();
        $cnt = Array();
        $itemids = Array();
        if ($rows) {
            $rows = $rows->fetchAll();
            if (sizeof(Opts::getOpt("window_ids")) == 1) {
                foreach ($rows as $r) {
                    $m[$r->itemid1][$r->itemid2] = $r->corr;
                    $m[$r->itemid2][$r->itemid1] = $r->corr;
                    $itemids[$r->itemid1] = true;
                    $itemids[$r->itemid2] = true;
                }
            } elseif (sizeof(Opts::getOpt("window_ids")) == 2) {
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

    function renderStats() {
        $rows = ItemCorr::icStats();
        if ($rows) {
            $this->exportdata = $rows->fetchAll();
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i = 0;
                foreach ($this->exportdata as $i => $row) {
                    $i++;
                    CliDebug::dbg(sprintf("Processing %d row of %d                 \r", $i, count($this->exportdata)));
                    $row["key1"] = IsPresenter::expandItem($row->itemid1, true);
                    $row["key2"] = IsPresenter::expandItem($row->itemid2, true);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }

    function renderWcorr() {
        $rows = ItemCorr::icTwStats();
        if ($rows) {
            $this->exportdata = $rows->fetchAll();
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i = 0;
                foreach ($this->exportdata as $i => $row) {
                    $i++;
                    CliDebug::dbg(sprintf("Processing %d row of %d                 \r", $i, count($this->exportdata)));
                    $row["key1"] = IsPresenter::expandItem($row->itemid1, true);
                    $row["key2"] = IsPresenter::expandItem($row->itemid2, true);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }

    public function renderLoi() {
        ItemCorr::IcLoi();
        self::mexit();
    }

    public function renderCompute() {
        if (Opts::isDefault("window_length")) {
            Opts::setOpt("window_length",Array(Monda::_1HOUR,Monda::_1DAY));
        }
        ItemCorr::IcMultiCompute();
        self::mexit(0, "Done\n");
    }

    public function renderDelete() {
        ItemCorr::IcDelete();
        self::mexit(0, "Done\n");
    }

}
