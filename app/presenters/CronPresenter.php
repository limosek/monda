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
     
     cron:1day
     
    [common opts]
     \n";
        $this->helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        return($ret);
    }
    
    public function render1day() {
        $opts=$this->opts;
        $opts->start=date_format(New \DateTime("00:00 yesterday"),"U");
        $opts->end=date_format(New \DateTime("00:00 today"),"U");
        $tw=New \App\Model\Tw($opts);
        $tw->twMultiCreate($opts);
        $hs=New \App\Model\HostStat($opts);
        
        $is=New \App\Model\ItemStat($opts);
        dump($is->isSearch($opts));
        $this->mexit();
    }
}