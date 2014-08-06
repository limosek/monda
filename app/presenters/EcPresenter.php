<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class EcPresenter extends BasePresenter
{
    
    public function Help() {
        \App\Model\CliDebug::warn("
     EventCorr operations
            
     ec:show [common opts]
     ec:compute [common opts]
     ec:delete [common opts]
     ec:loi [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=IsPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "corr",
                "Cr","corr",
                "Selector for windows to correlate with basic windows",
                "samewindow",
                "samewindow",
                Array("samewindow","samehour","samedow")
                );
        return($ret);
    }
    
    public function renderEc() {
        self::Help();
        self::mexit();
    }
    
    public function renderLoi() {
        \App\Model\EventCorr::IcLoi($this->opts);
        self::mexit();
    }
    
    public function renderCompute() {
        \App\Model\EventCorr::IcMultiCompute($this->opts);
        self::mexit(0,"Done\n");
    }
    
    public function renderDelete() {
        \App\Model\EventCorr::IcDelete($this->opts);
        self::mexit(0,"Done\n");
    }
}