<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\HostStat,
    App\Model\ItemCorr,
    App\Model\EventCorr,
    App\Model\Monda,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Exception,
    Nette\Utils\DateTime as DateTime;

class CronPresenter extends IsPresenter {

    public function Help() {
        echo "
     Cron operations
     
     cron:1hour
     cron:1day
     cron:1week
     cron:1month
     
    [common opts]
     \n";
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        TwPresenter::startup();
        HsPresenter::startup();
        IsPresenter::startup();
        IcPresenter::startup();
        EcPresenter::startup();

        Opts::addOpt(
                false, "sub_cron_targets", "Compute everything even for smaller cron targets (eg. for all weeks in month)", false, false
        );

        Opts::setDefaults();
        Opts::readCfg( Array("Is", "Hs", "Tw", "Ic", "Ec", "Cron"));
        Opts::readOpts($this->params);
        self::postCfg();
    }
    
    public static function postCfg() {
        TwPresenter::postCfg();
        HsPresenter::postCfg();
        IsPresenter::postCfg();
        IcPresenter::postCfg();
        EcPresenter::postCfg();
    }

    public function render1hour() {
        Opts::setOpt("window_length",Array(Monda::_1HOUR));
        if (Opts::isDefault("start")) {
            Opts::setOpt("start",date_format(New DateTime("121 minutes ago"), "U"));
            Opts::setOpt("end",date_format(New DateTime("61 minutes ago"), "U"));
        }
        self::renderRange(Monda::_1HOUR, "1hour");
    }

    public function render1day() {      
        if (Opts::isDefault("start")) {
            Opts::setOpt("start",date_format(New DateTime("00:00 yesterday"), "U"));
            Opts::setOpt("end",date_format(New DateTime("00:00 today"), "U"));
        }
        if (Opts::isDefault("window_length")) {
            Opts::setOpt("window_length",Array(Monda::_1HOUR, Monda::_1DAY));
        }
        self::renderRange(Monda::_1DAY, "1day");
    }

    public function render1week() {
       
        if (Opts::isDefault("start")) {
            $start=date_format(New DateTime("last monday 1 week ago"), "U");
            $end=date_format(New DateTime("last monday"), "U");
            if ($start==$end) {
                $end+=Monda::_1WEEK;
            }
            Opts::setOpt("start",$start);
            Opts::setOpt("end",$end);
        }

        if (Opts::isDefault("window_length")) {
            Opts::setOpt("window_length",Array(Monda::_1HOUR, Monda::_1DAY, Monda::_1WEEK));
        }
        self::renderRange(Monda::_1WEEK, "1week");
    }

    public function render1month() {
      
        if (Opts::isDefault("start")) {
            $monthago = date_format(New DateTime("1 month ago"), "U");
            $monthnow = time();
        } else {
            $monthago = Opts::getOpt("start");
        }
        $start = date_format(New DateTime(date("Y-m-01 00:00", $monthago)), "U");

        if (Opts::isDefault("window_length")) {
            Opts::setOpt("window_length",Array(Monda::_1HOUR, Monda::_1DAY, Monda::_1WEEK, Monda::_1MONTH, Monda::_1MONTH28, Monda::_1MONTH30, Monda::_1MONTH31));
        }
        $end=Opts::getOpt("end");
        while ($start < $end) {
            $monthlength = date("t", $start) * Monda::_1DAY;
            Opts::setOpt("start",$start);
            Opts::setOpt("end",$start+$monthlength);
            self::renderRange($monthlength, "1month");
            $start+=$monthlength;
            Opts::popOpt("start");
            Opts::popOpt("end");
        }
    }

    public function renderRange($step, $name, $preprocess = true, $postprocess = true) {
        $start=Opts::getOpt("start");
        $end=Opts::getOpt("end");
        if ($preprocess) {
            CliDebug::warn(sprintf("== $name preprocess-cron (%s to %s):\n", date("Y-m-d H:i", $start), date("Y-m-d H:i", $end)));
            self::precompute();
        }
        for ($s = $start; $s < $end; $s = $s + $step) {
            Opts::setOpt("start",$s);
            Opts::setOpt("end",$s+$step);
            $e = $s + $step;

            CliDebug::warn(sprintf("== $name cron (%s to %s):\n", date("Y-m-d H:i", $s), date("Y-m-d H:i", $e)));
            if (Opts::isOpt("sub_cron_targets")) {
                switch ($name) {
                    case "1month":
                        self::renderRange(Monda::_1WEEK, "1week", false, false);
                        self::compute($s, $e);
                        break;
                    case "1week":
                        self::renderRange(Monda::_1DAY, "1day", false, false);
                        self::compute($s, $e);
                        break;
                    case "1day":
                        self::renderRange(Monda::_1HOUR, "1hour", false, false);
                        self::compute($s, $e);
                        break;
                    case "1hour":
                        self::compute($s, $e, Monda::_1HOUR);
                        break;
                }
            } else {
                self::compute($start, $end, $step);
            }
        }
        if ($postprocess) {
            Opts::setOpt("start",$start);
            Opts::setOpt("end",$end);
            Opts::setOpt("window_empty",false);
            Opts::setOpt("window_length",Array(Monda::_1HOUR, Monda::_1DAY, Monda::_1WEEK, Monda::_1MONTH, Monda::_1MONTH28, Monda::_1MONTH30, Monda::_1MONTH31));
            CliDebug::warn(sprintf("== $name postprocess-cron (%s to %s):\n", date("Y-m-d H:i", $start), date("Y-m-d H:i", $end)));
            self::postcompute();
        }
        if ($preprocess && $postprocess) {
            self::mexit();
        }
    }

    public function precompute() {
        $s=Tw::twStats();
        CliDebug::info(sprintf("Window statistics: cnt=%d,minloi=%d,maxloi=%d,minprocessed=%d,maxprocessed=%d\n",$s->cnt,$s->minloi,$s->maxloi,$s->minprocessed,$s->maxprocessed));
        if (Opts::isOpt("dry")) {
            return(false);
        }
        try {
            Tw::twMultiCreate();
            Opts::setOpt("window_empty", true);
            Opts::setOpt("tw_minloi", -1);
            Opts::setOpt("window_sort", "start/+");
            ItemStat::IsMultiCompute();
            Tw::twLoi();
            ItemStat::IsLoi();
            HostStat::hsUpdate();
            HostStat::hsMultiCompute();
            HostStat::hsLoi();
            EventCorr::ecLoi();
        } catch (Exception $e) {
            CliDebug::warn("No itemstat to compute.\n");
        }
    }

    public function compute($s, $e, $l = false) {
        if (Opts::isOpt("dry")) {
            return(false);
        }

        Opts::setOpt("start", $s);
        Opts::setOpt("end", $e);
        if (!$l) {
            $l = Monda::_1WEEK;
        }
        try {
            $lengths = Array(Monda::_1HOUR, Monda::_1DAY, Monda::_1WEEK);
            foreach ($lengths as $length) {
                if ($length > $l)
                    continue;
                Opts::setOpt("corr_type", "samewindow");
                Opts::setOpt("window_length", Array($l));
                ItemCorr::IcMultiCompute();
                if ($length == Monda::_1HOUR) {
                    Opts::setOpt("corr_type", "samehour");
                    Opts::setOpt("window_length", Array(Monda::_1HOUR));
                    ItemCorr::IcMultiCompute();
                }
                if ($length >= Monda::_1DAY) {
                    Opts::setOpt("corr_type", "samedow");
                    Opts::setOpt("window_length", Array(Monda::_1DAY));
                    ItemCorr::IcMultiCompute();
                }
            }
        } catch (Exception $e) {
            CliDebug::warn("No interresting items found.\n");
        }
    }

    public function postcompute() {
        $s=Tw::twStats();
        CliDebug::info(sprintf("Window statistics: cnt=%d,minloi=%d,maxloi=%d,minprocessed=%d,maxprocessed=%d\n",$s->cnt,$s->minloi,$s->maxloi,$s->minprocessed,$s->maxprocessed));

        if (Opts::isOpt("dry")) {
            return(false);
        }
        try {
            ItemCorr::IcLoi();
        } catch (Exception $e) {
            
        }
    }

}
