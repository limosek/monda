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

Graphviz map operations
     
     gm:icw -w wid [common opts]
     gm:tws [common opts]
     gm:hs --hosts host [common opts]
     
    [common opts]
     \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    function startup() {
        parent::startup();
        TwPresenter::startup();
        HsPresenter::startup();
        IsPresenter::startup();
        IcPresenter::startup();
        EcPresenter::startup();

        Opts::addOpt(false, "gm_format", "Selector for output format to create", "gv", "gv", Array("svg", "png", "gv", "gv2")
        );
        Opts::addOpt(false, "gm_graph", "Selector for graphviz map type to create", "dot", "dot", Array("dot", "circo", "fdp")
        );
        Opts::addOpt(false, "gm_max_corrs_per_tw", "Maximum correlation per tw", 10, 10
        );
        Opts::addOpt(
                false, "anonymize_urls", "Anonymize urls", false, "no"
        );
        Opts::setDefaults();
        Opts::readCfg(Array("Tw", "Is", "Hs", "Ic", "Ec", "Map", "Gm"));
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

    function renderTws() {
        $this->setLayout(false);
        $wids = Tw::twToIds();
        $tree = Tw::twTree($wids);
        $stats = Tw::twStats();
        $this->template->title = "Monda TimeWindows";
        if (Opts::isDefault("corr_type")) {
            Opts::setOpt("corr_type", "samedow");
        }
        Opts::setOpt("hostname_restricted_chars","-");
        if (Opts::isDefault("gm_graph")) {
            Opts::setOpt("gm_graph","fdp");
        }
        $ics = ItemCorr::icTwStats();
        if (!$ics) {
            self::mexit(2,"No items to correlate.\n");
        }
        $ics=$ics->fetchAll();
        if (sizeof($ics) > 0) {
            foreach ($ics as $ic) {
                Opts::setOpt("window_ids", Array($ic->windowid1, $ic->windowid2));
                Opts::setOpt("max_rows", Opts::getOpt("gm_max_corrs_per_tw"));
                $itemids = ItemCorr::icToIds(false, true);
                $wcorr[$ic->windowid1][$ic->windowid2] = $ic->acorr ;
                $w1 = Tw::twGet($ic->windowid1);
                $w2 = Tw::twGet($ic->windowid2);
                $wcorrwids[$ic->windowid1]=1;
                $wcorrwids[$ic->windowid2]=1;
                $urls[$ic->windowid1][$ic->windowid2] = Util::ZabbixGraphUrl1($itemids, $w1->fstamp, $w1->seconds);
                $urls[$ic->windowid2][$ic->windowid1] = Util::ZabbixGraphUrl1($itemids, $w2->fstamp, $w2->seconds);
            }
            $this->template->wcorr = $wcorr;
            $this->template->wcorrwids = $wcorrwids;
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
        $this->template->setFile(APP_DIR . "/templates/gm/tws.latte");
        ob_start();
        $this->template->render();
        self::pipeOut(ob_get_clean());
    }

    function renderIcw() {
        $this->setLayout(false);
        Opts::setOpt("gm_graph","circo");
        $this->template->title = "Monda Correlations";
        $tws = Tw::twToIds();
        if (sizeof($tws) > 1) {
            self::mexit(2, "Specify only one window!\n");
        }
        $ics = ItemCorr::icSearch()->fetchAll();
        if (sizeof($ics) == 0) {
            self::mexit(2, "No correlations to report?\n");
        }
        $window = Tw::twGet($tws);
        $iccomb = Array();
        foreach ($ics as $i => $ic) {
            if ($ic->itemid1 == $ic->itemid2) {
                unset($ics[$i]);
                continue;
            }
            if (array_key_exists($ic->itemid2 . $ic->itemid1, $iccomb)) {
                unset($ics[$i]);
                continue;
            }
            $iccomb[$ic->itemid1 . $ic->itemid2] = 1;
            $ics[$i]->size = min($ic->corr * 5,0.7);
            $ics[$i]->label = sprintf("%.2f", $ic->corr);
            $info1 = ItemStat::iteminfo($ic->itemid1);
            $info2 = ItemStat::iteminfo($ic->itemid2);
            $iteminfo[$ic->itemid1] = $info1[0];
            $iteminfo[$ic->itemid2] = $info2[0];
            $hostid1 = $info1[0]->hostid;
            $hostid2 = $info2[0]->hostid;
            $hosts[$hostid1][] = $ic->itemid1;
            $hosts[$hostid2][] = $ic->itemid2;
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $hostsinfo[$hostid1] = HsPresenter::expandHost($info1[0]->hostid);
                $hostsinfo[$hostid2] = HsPresenter::expandHost($info2[0]->hostid);
                $itemsinfo[$ic->itemid1] = addslashes(IsPresenter::expandItem($ic->itemid1, true, true));
                $itemsinfo[$ic->itemid2] = addslashes(IsPresenter::expandItem($ic->itemid2, true, true));
                $ics[$i]->url = Util::ZabbixGraphUrl2(Array($ic->itemid1, $ic->itemid2), $window->fstamp, $window->seconds);
                $iurl[$ic->itemid1] = Util::ZabbixGraphUrl1(Array($ic->itemid1), $window->fstamp, $window->seconds);
                $iurl[$ic->itemid2] = Util::ZabbixGraphUrl1(Array($ic->itemid2), $window->fstamp, $window->seconds);
            } else {
                $hostsinfo[$hostid1] = $info1[0]->hostid;
                $hostsinfo[$hostid2] = $info2[0]->hostid;
                $itemsinfo[$ic->itemid1] = $ic->itemid1;
                $itemsinfo[$ic->itemid2] = $ic->itemid2;
                $ics[$i]->url = "";
            }
        }
        $this->template->wid = $tws;
        $this->template->hosts = $hosts;
        $this->template->hostsinfo = $hostsinfo;
        $this->template->itemsinfo = $itemsinfo;
        $this->template->iurl = $iurl;
        $this->template->ics = $ics;
        $this->template->window = $window;
        $this->template->setFile(APP_DIR . "/templates/gm/icw.latte");
        ob_start();
        $this->template->render();
        self::pipeOut(ob_get_clean());
    }

    public function renderHs() {
        $this->setLayout(false);
        parent::renderHs();
        $this->template->setFile(APP_DIR . "/templates/gm/hs.latte");
        ob_start();
        $this->template->render();
        self::pipeOut(ob_get_clean());
    }

    function pipeOut($data) {
        switch (Opts::getOpt("gm_format")) {
            case "gv":
                echo $data;
                exit;
                break;
            case "gv2":
                $of = "";
                break;
            case "svg":
                $of = "-Tsvg";
                break;
            case "png":
                $of = "-Tpng";
                break;
            default:
                ob_end_clean();
                self::mexit(2,"Unknown output format.");
                break;
        }
        $dspec = array(
            0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            1 => array("pipe", "w")  // stdout is a pipe that the child will write to
        );
        $pipes = Array();
        $env = Array();
        CliDebug::dbg(Opts::getOpt("gm_graph") . " $of");
        $process = proc_open(Opts::getOpt("gm_graph") . " $of", $dspec, $pipes, getcwd(), $env);
        if (is_resource($process)) {
            fwrite($pipes[0], $data);
            fclose($pipes[0]);
            echo stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $return_value = proc_close($process);
        } else {
            self::mexit(2, "Cannot find " . Opts::getOpt("gm_graph") . "?");
        }
        exit($return_value);
    }

}
