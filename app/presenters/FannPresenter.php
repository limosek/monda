<?php

namespace App\Presenters;

use App\Model\ItemStat,
    App\Model\Tw,
    App\Model\HostStat,
    App\Model\ItemCorr,
    App\Model\EventCorr,
    App\Model\Monda,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    App\Model\Fann,
    Exception,
    Nette\Utils\DateTime as DateTime;

class FannPresenter extends BasePresenter {

    public function Help() {
        echo "
     Fast neural network operations
     
     fann:train
     fann:test
     
    [common opts]
     \n";
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        TwPresenter::startup();
        HsPresenter::startup();
        IsPresenter::startup();
        IcPresenter::startup();
        EcPresenter::startup();

       /* Opts::addOpt(
                false, "sub_cron_targets", "Compute everything even for smaller cron targets (eg. for all weeks in month)", false, false
        );
*/
        Opts::setDefaults();
        Opts::readCfg( Array("Is", "Hs", "Tw", "Ic", "Ec", "Cron","Fann"));
        Opts::readOpts($this->params);
        self::postCfg();
    }
    
    public static function postCfg() {
        TwPresenter::postCfg();
        HsPresenter::postCfg();
        IsPresenter::postCfg();
        IcPresenter::postCfg();
        EcPresenter::postCfg();
    }
    
    public function renderTrain() {
        Fann::train();
        self::mexit();
    }

}
