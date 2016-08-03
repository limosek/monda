<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\ItemCorr,
    App\Model\Monda, 
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Nette\Utils\DateTime as DateTime;

class IcPresenter extends BasePresenter {

    public function Help() {
        CliDebug::warn("
     ItemCorr operations
            
     ic:show [common opts]
     ic:stats [commom opts]
     ic:matrix [common opts]
     ic:history [common opts]
     ic:compute [common opts]
     ic:delete [common opts]
     ic:loi [common opts]
 
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
                false, "max_values_for_corr", "Maximum values to make correlation", 1000, 1000
        );
        Opts::addOpt(
                false, "min_corr", "Minimum correlation to report (bigger than)", 0.4, 0.4
        );
        Opts::addOpt(
                false, "max_corr", "Maximum correlation to report (less than)", 1, 1
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
        $rows = ItemCorr::icSearch();
        if ($rows) {
            $this->exportdata = $rows->fetchAll();
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
        }
        self::mexit();
    }
    
    public function renderHistory() {
        Opts::setOpt("ic_sort", "start/+");
        $rows = ItemCorr::icToIds();
        $tws = Tw::twToIds();
        foreach ($tws as $tw) {
            Opts::setOpt("window_ids", Array($tw));
            $items = ItemCorr::icSearch()->fetchAll();
            foreach ($items as $item) {
                $this->exportdata[$item->windowid1] = Array(
                    "windowid" => $item->windowid1,
                    "itemid1" => $item->itemid1,
                    "itemid2" => $item->itemid2,
                    "corr" => $item->corr
                );
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
