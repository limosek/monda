<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class GnuPlotPresenter extends BasePresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     Gnuplot operations
  
     gp:tw [common opts]
 
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
    
    function renderTw() {
        $opts=$this->opts;
        $this->setLayout(false);
        $this->template->title = "Monda TimeWindow Graphs";
        $wids=  Model\Tw::twToIds($opts);
        $itemids = Model\ItemStat::isToIds($opts);
        $history=Model\ItemStat::IsZabbixHistory($opts);
        $this->template->history=$history;
    }
    
    function renderData() {
        $opts = $this->opts;
        $wids = Model\Tw::twToIds($opts);
        $itemids = Model\ItemStat::isToIds($opts);
        $history = Model\ItemStat::IsZabbixHistory($opts);
        $tl = Array();
        $itemsfound=Array();
        foreach ($history as $itemid => $item) {
            foreach ($item as $clock => $value) {
                $tl[$clock][$itemid] = $value;
                $itemsfound[$itemid]=$itemid;
            }
        }
        reset($itemids);
        echo "c;";
        foreach ($itemsfound as $i) {
            echo "i$i;";
        }
        echo "\n";
        reset($tl);
        foreach ($tl as $c=>$clock) {
            reset($itemsfound);
            echo "$c;";
            foreach ($itemsfound as $i) {
                if (array_key_exists($i, $clock)) {
                    echo $clock[$i];
                } else {
                    echo "nan";
                }
                echo ";";
            }
            echo "\n";
        }
        self::mexit();
    }

}
