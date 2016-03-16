<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\HostStat,
    App\Model\Monda,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Nette\Utils\DateTime as DateTime;

class HsPresenter extends BasePresenter {

    private $hs;

    public function Help() {
        CliDebug::warn("
     Host operations
            
     hs:show [common opts]
     hs:stats [common opts]
     hs:update [common opts]
        Update hostids for itemids in monda db
     hs:delete [common opts]
     hs:compute [common opts]
        Compute stats based on host and itemids
     
    [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        $tw = new TwPresenter();
        $tw->startup();
        Opts::addOpt(
                false, "hostids", "Hostids to get", false, "All"
        );

        Opts::addOpt(
                false, "hostgroups", "Hostgroups to get", "monda", "monda"
        );

        Opts::addOpt(
                false, "hosts", "Hostnames to get", false, "All"
        );
        Opts::setDefaults();
        Opts::readCfg(Array("Hs"));
        Opts::readOpts($this->params);
        self::postCfg();
    }

    static function postCfg() {
        Opts::optToArray("hostgroups");
        Opts::optToArray("hostids");
        Opts::optToArray("hosts");
        HostStat::hostsToIds();
        if (is_array(Opts::getOpt("hostids"))) {
            CliDebug::dbg(sprintf("Hostids selected: %s\n", join(",", $ret->hostids)));
        }
        return;
    }

    function expandHost($hostid) {
        $iq = Array(
            "monitored" => true,
            "output" => "extend",
            "hostids" => array($hostid)
        );
        $h = Monda::apiCmd("hostGet", $iq);
        if (count($h) > 0) {
            return($h[0]->host);
        } else {
            return("unknown");
        }
    }

    public function renderShow() {
        $rows = HostStat::hsSearch();
        if ($rows) {
            $this->exportdata = $rows->fetchAll();
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i = 0;
                foreach ($this->exportdata as $i => $row) {
                    $i++;
                    CliDebug::dbg(sprintf("Processing %d row of %d          \r", $i, count($this->exportdata)));
                    $row["host"] = HsPresenter::expandHost($row->hostid);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }

    public function renderStats() {
        $rows = HostStat::hsStats();
        if ($rows) {
            $this->exportdata = $rows->fetchAll();
            if (Opts::getOpt("output_verbosity") == "expanded") {
                $i = 0;
                foreach ($this->exportdata as $i => $row) {
                    $i++;
                    CliDebug::dbg(sprintf("Processing %d row of %d          \r", $i, count($this->exportdata)));
                    $row["host"] = HsPresenter::expandHost($row->hostid);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }

    public function renderCompute() {
        HostStat::hsMultiCompute();
        self::mexit();
    }

    public function renderLoi() {
        HostStat::hsLoi();
        self::mexit();
    }

    public function renderUpdate() {
        HostStat::hsUpdate();
        self::mexit();
    }

    public function renderDelete() {
        HostStat::hsDelete();
        self::mexit();
    }

}
