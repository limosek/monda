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
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=HsPresenter::getOpts($ret);
        $ret=IsPresenter::getOpts($ret);
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