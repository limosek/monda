<?php

namespace App\Presenters;

use \Exception,
    App\Model\Opts,
    App\Model\CliDebug,
    App\Model\Util,
    App\Model\Tw,
    App\Model\Monda,
    Tracy\Debugger,
    Nette\Utils\DateTime as DateTime;

class TwPresenter extends BasePresenter {

    public function startup() {
        parent::startup();
        
        Opts::addOpt(
                "s", "start", "Start time of analysis.", date_format(New DateTime(date("Y-01-01 00:00P")), "U"), date("Y-01-01 00:00")
        );
        Opts::addOpt(
                "e", "end", "End time of analysis.", Util::roundtime(time() - 3600), "-1 hour"
        );
        Opts::addOpt(
                "d", "window_description", "Window description.", "", ""
        );
        Opts::addOpt(
                "l", "window_length", "Window length. Leave empty to get all lengths.", Array(Monda::_1HOUR, Monda::_1DAY, Monda::_1WEEK, Monda::_1MONTH, Monda::_1MONTH28, Monda::_1MONTH30, Monda::_1MONTH31), "All"
        );
        Opts::addOpt(
                false, "window_empty", "Work only with empty (non-computed) windows.", false, "All"
        );
        Opts::addOpt(
                "ws", "window_sort", "Sort order of windows to select ({random|start|length|loi|loih|updated}/{+|-}", "loi/-", "loi/-"
        );
        Opts::addOpt(
                "u", "window_updated_before", "Select only windows which were updated less than datetime", false, "no care"
        );
        Opts::addOpt(
                "w", "window_ids", "Select only windows with this ids", false, "no care"
        );
        Opts::addOpt(
                "Cl", "window_change_loi", "Change loi of selected windows. Can be number, +number or -number", false, "None"
        );
        Opts::addOpt(
                "Rn", "window_rename", "Rename selected window(s). Can contain macros %Y, %M, %d, %H, %i, %l, %F", false, "None"
        );
        Opts::addOpt(
                false, "tw_minloi", "Minimum timewindow loi to search.", 0, 0
        );
        Opts::setDefaults();
        Opts::readCfg(Array("global", "Tw"));
        Opts::readOpts($this->params);
        self::postCfg();
    }
    
    static public function postCfg() {
        parent::postCfg();
        Opts::setOpt("start", Util::timetoseconds(Opts::getOpt("start")));
        Opts::setOpt("end", Util::timetoseconds(Opts::getOpt("end")));
        
        if (Opts::isOpt("window_length")) {
            $lengths=Opts::optToArray("window_length");
            foreach ($lengths as $id => $l) {
                if (!is_numeric($l)) {
                    $lengths[$id] = Util::timetoseconds($l) - time();
                }
            }
            Opts::setOpt("window_length", $lengths);
        }
        if (Opts::isOpt("window_ids")) {
            Opts::optToArray("window_ids");
        }
        if (Opts::getOpt("start") < 631148400) {
            self::mexit(4, sprintf("Bad start time (%d)?!\n", date("Y-m-d", Opts::getOpt("start"))));
        }
    }
    
    public function Help() {
        CliDebug::warn("
     Time Window operations
     
     tw:create [common opts]
        Create window(s) for specified period and length

     tw:delete [common opts]
        Remove windows and dependent data from this range
     
    tw:empty [common opts]
        Empty windows data but leave windows created
        
     tw:show
        Show informations about timewindows in db
        
     tw:stats
        Show statistics about timewindows in db
    
    tw:zstats
        Show statistics about zabbix data at timewindows
    
    tw:modify
        Modify or rename window(s)
        
     tw:loi
        Recompute Level of Interest for windows
     
     Date formats: @timestamp, YYYYMMDDhhmm, now, '1 day ago', '00:00 1 day ago'
     TimeWindow formats: Date_format/length, Date_format-Date_format/length, id
     If no start and end date given, all data will be affected.
     
    [common opts]
     \n");
       Opts::helpOpts(); 
       Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function expandTw($wid) {
        $w = Tw::twGet($wid);
        $wstr = sprintf("%s/%d(%s)", $w->tfrom, $w->seconds, $w->description);
        return($wstr);
    }

    public function renderShow() {
        $windows = Tw::twSearch();
        $this->exportdata = $windows->fetchAll();
        parent::renderShow($this->exportdata);
        self::mexit();
    }

    public function renderStats() {
        $this->exportdata = Array(Tw::twStats());
        parent::renderShow($this->exportdata);
        self::mexit();
    }

    public function renderZStats() {
        $this->exportdata = Tw::twZstats();
        parent::renderShow($this->exportdata);
        self::mexit();
    }

    public function renderLoi() {
        Tw::twLoi();
        self::mexit();
    }

    public function renderModify() {
        Tw::twModify();
        self::mexit();
    }

    public function renderCreate() {
        if (!Tw::twMultiCreate()) {
            self::mexit(5, "No window lengths specified! Use -l!\n");
        }
        self::mexit();
    }

    public function renderDelete() {
        Tw::twDelete();
        self::mexit();
    }

    public function renderEmpty() {
        Tw::twEmpty();
        self::mexit();
    }

}
