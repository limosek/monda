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
  
     gm:tw [common opts]
 
     [common opts]
    \n");
        self::helpOpts();
    }

    function renderTl() {
        $this->setLayout(false);
        $this->template->title = "Monda Timeline";
        parent::renderTl();
        //Dump($this->template->map);exit;
    }

}
