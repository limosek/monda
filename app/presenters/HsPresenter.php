<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\HostStat,
    App\Model\Monda,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    App\Model\Util,
    Nette\Utils\DateTime as DateTime;

class HsPresenter extends BasePresenter {

    private $hs;

    public function Help() {
        CliDebug::warn("

HostStats operations
            
     hs:show [common opts]
        Show hoststat

     hs:stats [common opts]
        Show hoststat statistics

     hs:update [common opts]
        Update hostids for itemids in monda db (link hosts and items in Monda db)
        
     hs:delete [common opts]
        Delete hoststats
        
     hs:compute [common opts]
        Compute stats based on host and itemids.
     
    [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        TwPresenter::startup();
        
        Opts::addOpt(
                false, "hostids", "Hostids to get", false, "All"
        );
        Opts::addOpt(
                false, "hostgroups", "Hostgroups to get", "monda", "monda"
        );
        Opts::addOpt(
                false, "hosts", "Hostnames to get", false, "All"
        );
        Opts::addOpt(
                false, "hs_minloi", "Minimum LOI to select hosts", 0, 0
        );
        Opts::addOpt(
                false, "anonymize_hosts", "Anonymize host names", false, "no"
        );
        Opts::addOpt(
                false, "hostname_restricted_chars", "Characters mangled in hostnames", false, "none"
        );
        Opts::addOpt(
                false, "hs_update_unknown", "(Re)-update even unknown hosts (slow).", false, "no"
        );
        Opts::addOpt(
                false, "hs_max_rows", "Maximum number of hoststats to get (LIMIT for SELECT)", 300, 300
        );
        
        Opts::setDefaults();
        Opts::readCfg(Array("Hs"));
        Opts::readOpts($this->params);
        self::postCfg();
        if ($this->action=="stats") {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("hostid","host","loi"));
            }
        } else {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("hostid","host","windowid","loi"));
            }
        }
        if (Opts::isDefault("tw_max_rows")) {
            Opts::setOpt("tw_max_rows",false);
        }
        if (Opts::isDefault("is_max_rows")) {
            Opts::setOpt("is_max_rows",false);
        }
    }

    static function postCfg() {
        TwPresenter::postCfg();
        Opts::optToArray("hostgroups");
        Opts::optToArray("hostids");
        Opts::optToArray("hosts");
        if (!is_array(Opts::getOpt("hostids"))) {
            HostStat::hostsToIds();
        }
        if (is_array(Opts::getOpt("hostids"))) {
            CliDebug::dbg(sprintf("Hostids selected: %s\n", join(",", Opts::getOpt("hostids"))));
        }
        if (!Opts::getOpt("anonymize_key") && Opts::getOpt("anonymize_hosts")) {
            self::mexit(2,"You must use anonymize_key to anonymize hosts.");
        }
    }

    static function expandHost($hostid) {
        $iq = Array(
            "monitored" => true,
            "output" => "extend",
            "hostids" => array($hostid)
        );
        $h = Monda::apiCmd("hostGet", $iq);
        if (count($h) > 0) {
            if (Opts::getOpt("anonymize_hosts")) {
                return(Util::anonymize($h[0]->host,Opts::getOpt("anonymize_key")));
            } else {
                if (Opts::getOpt("hostname_restricted_chars")) {
                    return(strtr($h[0]->host,Opts::getOpt("hostname_restricted_chars"),"_____________"));
                } else {
                    return($h[0]->host);
                }
            }
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
        } else {
            self::helpEmpty();
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
        } else {
            self::helpEmpty();
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
        HostStat::hsLoi();
        self::mexit();
    }

    public function renderDelete() {
        HostStat::hsDelete();
        self::mexit();
    }

}
