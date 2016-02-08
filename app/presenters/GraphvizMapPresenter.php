<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class GraphvizMapPresenter extends MapPresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     Graphviz Map operations
  
     gm:tl [common opts]
     gm:hs [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    function renderTl() {
        $this->setLayout(false);
        $this->template->title = "Monda TimeWindow Hierarchy";
        parent::renderTl();
    }

    function renderHs() {
        $this->setLayout(false);
        $this->template->title = "Monda Hosts Stats";
        parent::renderHs();
    }

}
