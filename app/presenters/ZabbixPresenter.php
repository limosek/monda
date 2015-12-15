<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    Nette\Utils\DateTime as DateTime;

class ZabbixPresenter extends MapPresenter {

    public function Help() {
        \App\Model\CliDebug::warn("
     Zabbix Map operations

     zabbix:twgraph  - Create zabbix 
 
     [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=self::parseOpt($ret,
                "rwhost",
                false,"rwhost",
                "Host in Zabbix with readwrite access to create objects.",
                "monda",
                "monda"
                );
        return($ret);
    }
    
    function renderTwGraph() {
        
        parent::renderTw();
        $this->template->title = "Timewindow ".$this->opts->wids[0];
        $colors=Array(
            1 => "001100",
            2 => "002200",
            3 => "003300",
            4 => "004400",
            5 => "005500",
            6 => "006600",
            7 => "007700",
            8 => "008800",
            9 => "009900",
            10 => "00aa00"
        );
        $color=1;
        $gs=  \App\Model\Monda::apiCmd("graphGet",Array(
            "itemids" => $this->template->top10items
        ));
        $gid=false;
        foreach ($gs as $g) {
            if ($g->name=="monda_test") $gid=$g->graphid;
        }
        if ($gid) {
            \App\Model\Monda::apiCmd("graphDelete",Array($gid));
        }
        foreach ($this->template->top10items as $i) {
            $gitems[] = Array(
                    "itemid" => $i,
                    "color" => $colors[$color++]
                );
        }
        $r=  \App\Model\Monda::apiCmd("graphCreate",
                Array(
                    "name" => "monda_test",
                    "width" => 800,
                    "height" => 600,
                    "gitems" => $gitems
                ));
        dump($r);exit;
    }

}
