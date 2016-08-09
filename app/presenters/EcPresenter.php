<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\EventCorr,
    App\Model\Monda,
    App\Model\TriggerInfo,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    Nette\Utils\DateTime as DateTime;

class EcPresenter extends BasePresenter {

    public function Help() {
        CliDebug::warn("
     EventCorr operations
            
     ec:show [common opts]
     ec:loi [common opts]
 
     [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        IsPresenter::startup();

        Opts::addOpt(false, "ec_item_increment_loi", "Increase LOI for item with event", 50, 50
        );
        Opts::addOpt(false, "ec_window_increment_loi", "Increase LOI for time window with event", 10, 10
        );
        Opts::addOpt(false, "ec_host_increment_loi", "Increase LOI for time host with event", 5, 5
        );
        Opts::addOpt(false, "ec_min_priority", "Minimum priority of trigger", 3, 3
        );

        Opts::setDefaults();
        Opts::readCfg(Array("Ec"));
        Opts::readOpts($this->params);
        self::postCfg();
    }

    public static function postCfg() {
        IsPresenter::postCfg();
    }

    public function renderShow() {
        $events=EventCorr::ecSearch(Opts::getOpt("start"));
        foreach ($events as $e) {
            if (isset($e->relatedObject->triggerid)) {
                    $tq=Array(
                        "triggerids" => $e->relatedObject->triggerid,
                        "hostids" => Opts::getOpt("hostids"),
                        "selectFunctions" => "extend",
                        "output" => "extend"
                    );
                    $trigger=Monda::apiCmd("triggerGet",$tq);
                }
                if (Opts::getOpt("output_verbosity")=="expanded") {
                    $tdesc=TriggerInfo::expandTrigger($trigger[0]->triggerid,true);
                } else {
                    $tdesc="";
                }
            $this->exportdata[]=Array (
                "eventid" => $e->eventid,
                "triggerid" => $e->relatedObject->triggerid,
                "priority" => $t->priority,
                "clock" => $e->clock,
                "value" => $e->value,
                "trigger" => $tdesc
            );
        }
        parent::renderShow($this->exportdata);
        self::mexit();
    }
    
    public function renderLoi() {
        EventCorr::EcLoi();
        self::mexit();
    }

}
