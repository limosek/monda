<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class HtmlPresenter extends MapPresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     HTML Map operations
            
     hm:tw [common opts] Information about timewindow
     hm:month [common opts] Monthly information about timewindows
     hm:year [common opts] Yearly information about timewindows
     hm:tl [common opts] Timewindows tree
 
     [common opts]
    \n");
        self::helpOpts();
    }

    function renderTl() {
        $this->template->title = "Monda Timeline";
        parent::renderTl();
    }
    
    function renderMonth() {
        $this->template->title = "Monda month overview";
        parent::renderMonth();
    }
    
    function renderTw() {
        parent::renderTw();
        $this->template->title = "Timewindow ".$this->opts->wids[0];
    }

}
