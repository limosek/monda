<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

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
     
    [common opts]
     \n";
        $this->helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        return($ret);
    }
    
    public function render1hour() {
        $opts=$this->opts;
        $opts->start=date_format(New \DateTime("2 hour ago"),"U");
        $opts->end=date_format(New \DateTime(),"U");
        $opts->length=Array(3600);
        $tw=New \App\Model\Tw($opts);
        $tw->twMultiCreate($opts);        
        $is=New \App\Model\ItemStat($opts);
        $opts->empty=true;
        $is->IsMultiCompute($opts);
        $is->IsLoi($opts);
        $hs=New \App\Model\HostStat($opts);
        $hs->hsMultiCompute($opts);
        $hs->hsLoi($opts);
        $this->mexit();
    }
    
    public function render1day() {
        $opts=$this->opts;
        $opts->start=date_format(New \DateTime("00:00 yesterday"),"U");
        $opts->end=date_format(New \DateTime("00:00 today"),"U");
        $opts->length=Array(3600,86400);
        $this->compute($opts);
    }
    
    public function render1week() {
        $opts=$this->opts;
        $opts->start=date_format(New \DateTime("00:00 1 week ago"),"U");
        $opts->end=date_format(New \DateTime("00:00 today"),"U");
        $opts->length=Array(3600,86400,86400*7);
        $this->compute($opts);
    }
    
    public function render1month() {
        $opts=$this->opts;
        $monthago=date_format(New \DateTime("1 month ago"),"U");
        $opts->start=date_format(New \DateTime(date("Y-m-01 00:00",$monthago)),"U");
        $opts->end=date_format(New \DateTime("00:00 today"),"U");
        $opts->length=Array(3600,3600*24,3600*24*31);
        $this->compute($opts);
    }
    
    public function compute($opts) {
        $tw=New \App\Model\Tw($opts);
        $tw->twMultiCreate($opts);        
        $is=New \App\Model\ItemStat($opts);
        $opts->empty=true;
        $is->IsMultiCompute($opts);
        $opts->empty=false;
        $is->IsLoi($opts);
        $hs=New \App\Model\HostStat($opts);
        $hs->hsMultiCompute($opts);
        $hs->hsLoi($opts);
        $ic=New \App\Model\ItemCorr($opts);
        $opts->icempty=true;
        $opts->maxicrows=100;
        $opts->corr="samewindow";
        $ic->IcMultiCompute($opts);
        $opts->corr="samehour";
        $opts->length=3600;
        $ic->IcMultiCompute($opts);
        $opts->corr="samedow";
        $opts->length=3600*24;
        $ic->IcMultiCompute($opts);
        $ic->IcLoi($opts);
        $this->mexit();
    }
}