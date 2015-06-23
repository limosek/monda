<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class EcPresenter extends BasePresenter
{
    
    public function Help() {
        \App\Model\CliDebug::warn("
     EventCorr operations
            
     ec:show [common opts]
     ec:loi [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    public static function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=IsPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "inc_loi_event_itemstat",
                false,"inc_loi_event_item",
                "Increase LOI for item with event",
                100,
                100
                );
        $ret=self::parseOpt($ret,
                "inc_loi_event_window",
                false,"inc_loi_event_window",
                "Increase LOI for time window with event",
                100,
                100
                );
        $ret=self::parseOpt($ret,
                "inc_loi_event_host",
                false,"inc_loi_event_host",
                "Increase LOI for time host with event",
                100,
                100
                );
        return($ret);
    }
    
    public function renderEc() {
        self::Help();
        self::mexit();
    }
    
    public function renderLoi() {
        \App\Model\EventCorr::EcLoi($this->opts);
        self::mexit();
    }
}