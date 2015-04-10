<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class HtmlMapPresenter extends MapPresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     HTML Map operations
            
     hm:tw [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }

    function renderTl() {
        $this->template->title = "Monda Timeline";
        parent::renderTl();
    }
    
    function renderTw() {
        parent::renderTw();
        $this->template->title = "Timewindow ".$this->opts->wids;
    }

}
