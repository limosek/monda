<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class CronPresenter extends IsPresenter
{
    
    public function renderDefault() {
        $this->Help();
        $this->mexit();
    }
    
    public function renderCron() {
        $this->Help();
        $this->mexit();
    }
    
    public function Help() {
        echo "
     Cron operations
     
     cron:1hour
     cron:1day
     cron:1week
     cron:1month
     
    [common opts]
     \n";
        $this->helpOpts();
    }
    
    public function getOpts($opts) {
        $ret=parent::getOpts($opts);
        $opts=TwPresenter::getOpts($opts);
        $opts=HsPresenter::getOpts($opts);
        $opts=IsPresenter::getOpts($opts);
        $opts=IcPresenter::getOpts($opts);
        $ret=self::parseOpt($ret,
                "subcron",
                "Sc","sub_cron_targets",
                "Compute everything even for smaller cron targets (eg. for all weeks in month)",
                false,
                false
                );
        return($opts);
    }
    
    public function render1hour($aopts=false) {
        if (!$aopts) {
            $opts=$this->opts;
        } else {
            $opts=$aopts;
        }
        $opts->length=Array(\App\Model\Monda::_1HOUR);
        if ($this->isOptDefault("start") && !$aopts) {
            $opts->start=date_format(New DateTime("121 minutes ago"),"U");
            $opts->end=date_format(New DateTime("61 minutes ago"),"U");
        }
        self::renderrange($opts,$opts->start,$opts->end,\App\Model\Monda::_1HOUR,"1hour");
        if (!$aopts) {
            parent::mexit();
        }
    }
    
    public function render1day($aopts=false) {
        if (!$aopts) {
            $opts=$this->opts;
        } else {
            $opts=$aopts;
        }
        if ($this->isOptDefault("start") && !$aopts) {
            $opts->start=date_format(New DateTime("00:00 yesterday"),"U");
            $opts->end=date_format(New DateTime("00:00 today"),"U");
        }
        if ($this->isOptDefault("length")) {
            $opts->length=Array(\App\Model\Monda::_1HOUR,\App\Model\Monda::_1DAY);
        }
        
        self::renderrange($opts,$opts->start,$opts->end,\App\Model\Monda::_1DAY,"1day");
        if (!$aopts) {
            parent::mexit();
        }
    }
    
    public function render1week($aopts=false) {
        if (!$aopts) {
            $opts=$this->opts;
        } else {
            $opts=$aopts;
        }
        if ($this->isOptDefault("start") && !$aopts) {
            $start=date_format(New DateTime("last monday 1 week ago"),"U");
            $end=date_format(New DateTime("last monday"),"U");
            if ($start==$end) {
                $end+=\App\Model\Monda::_1WEEK;
            }
        } else {
            $start=$opts->start;
            $end=$opts->end;
        }
        
        if ($this->isOptDefault("length")) {
            $opts->length=Array(\App\Model\Monda::_1HOUR,\App\Model\Monda::_1DAY,\App\Model\Monda::_1WEEK);
        }        
        self::renderRange($opts,$start,$end, \App\Model\Monda::_1WEEK, "1week");
        if (!$aopts) {
            parent::mexit();
        }
    }
    
    public function render1month($aopts=false) {
        if (!$aopts) {
            $opts=$this->opts;
        } else {
            $opts=$aopts;
        }
        if ($this->isOptDefault("start") && !$aopts) {
            $monthago=date_format(New DateTime("1 month ago"),"U");
            $monthnow=time();
        } else {
            $monthago=$opts->start;
            $monthnow=$monthago+\App\Model\Monda::_1MONTH;
        }
        $start=date_format(New DateTime(date("Y-m-01 00:00",$monthago)),"U");
        $end=date_format(New DateTime(date("Y-m-01 00:00",$monthnow)),"U");
        
        if ($this->isOptDefault("length")) {
            $opts->length=Array(\App\Model\Monda::_1HOUR,\App\Model\Monda::_1DAY,\App\Model\Monda::_1MONTH);
        }
        self::renderrange($opts,$start,$end,\App\Model\Monda::_1MONTH,"1month");
        if (!$aopts) {
            parent::mexit();
        }
    }
    
    public function renderRange($opts,$start,$end,$step,$name,$preprocess=true,$postprocess=true) {
        $opts->start=$start;
        $opts->end=$end;
        
        if ($preprocess) {
            \App\Model\CliDebug::warn(sprintf("== $name preprocess-cron (%s to %s):\n",date("Y-m-d H:i",$start),date("Y-m-d H:i",$end)));
            self::precompute($opts);
        }
        for ($s=$start;$s<$end;$s=$s+$step) {
            $opts->start=$s;
            $opts->end=$s+$step;
            $e=$s+$step;
           
            \App\Model\CliDebug::warn(sprintf("== $name cron (%s to %s):\n",date("Y-m-d H:i",$s),date("Y-m-d H:i",$e)));
            if ($opts->subcron) {
                switch ($name) {
                    case "1month":
                        self::renderRange($opts,$s,$e, \App\Model\Monda::_1WEEK,"1week",false,false);
                        self::compute($opts,$s,$e);
                        break;
                    case "1week":
                        self::renderRange($opts,$s,$e, \App\Model\Monda::_1DAY,"1day",false,false);
                        self::compute($opts,$s,$e);
                        break;
                    case "1day":
                        self::renderRange($opts,$s,$e, \App\Model\Monda::_1HOUR,"1hour",false,false);
                        self::compute($opts,$s,$e);
                        break;
                    case "1hour":
                        self::compute($opts,$s,$e,3600);
                        break;
                }
            } else {
                self::compute($opts,$start,$end,$step);
            }
        }
        if ($postprocess) {
            \App\Model\CliDebug::warn(sprintf("== $name postprocess-cron (%s to %s):\n",date("Y-m-d H:i",$start),date("Y-m-d H:i",$end)));
            self::postcompute($opts);
        }
    }
    
    public function precompute($opts) {
        if ($opts->dry) {
            return(false);
        }
        \App\Model\Tw::twMultiCreate($opts);
        $opts->empty=true;
        \App\Model\ItemStat::IsMultiCompute($opts);
        $opts->empty=false;
        \App\Model\Tw::twLoi($opts);
        \App\Model\ItemStat::IsLoi($opts);
        \App\Model\HostStat::hsUpdate($opts);
        \App\Model\HostStat::hsMultiCompute($opts);
        \App\Model\HostStat::hsLoi($opts);
        \App\Model\EventCorr::ecLoi($opts);
    }
    
    public function compute($opts,$s,$e,$l=false) {
        if ($opts->dry) {
            return(false);
        }
        
        $opts->start=$s;
        $opts->end=$e;
        if (!$l) {
            $l=\App\Model\Monda::_1WEEK;
        }

        $lengths=Array(\App\Model\Monda::_1HOUR,\App\Model\Monda::_1DAY,\App\Model\Monda::_1WEEK);
        $opts->isloionly=true;
        foreach ($lengths as $length) {
            if ($length>$l) continue; 
            $opts->corr="samewindow";
            $opts->length=Array($length);
            \App\Model\ItemCorr::IcMultiCompute($opts);
            if ($length==\App\Model\Monda::_1HOUR) {
                $opts->corr="samehour";
                $opts->length=Array(\App\Model\Monda::_1HOUR);
                \App\Model\ItemCorr::IcMultiCompute($opts);
            }
            if ($length>=\App\Model\Monda::_1DAY) {
                $opts->corr="samedow";
                $opts->length=Array(\App\Model\Monda::_1HOUR);
                \App\Model\ItemCorr::IcMultiCompute($opts);
            }
        }
    }
    
    public function postcompute($opts) {
        if ($opts->dry) {
            return(false);
        }
        \App\Model\ItemCorr::IcLoi($opts);
    }
}