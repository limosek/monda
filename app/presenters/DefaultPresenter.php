<?php


namespace App\Presenters;

use App\Model\CliDebug, App\Model\Opts,
    Tracy\Debugger,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class DefaultPresenter extends BasePresenter
{

    public function Help()
    {
        CliDebug::warn("

Monitoring system data analysis
     
     You can use one of this commands:
     
     tw             - TimeWindow operations
     is             - ItemStat operations
     hs             - HostStat operations
     ic             - Item Correlation operations
     ec             - Event Correlation operations
     cron           - Combining all operations from cron
     gm             - Create graphviz maps
     
     Hint: Date formats: @timestamp, YYYY_MM_DD_hhmm, YYYYMMDDhhmm, now, '-1 day'
     Hint: Divide more ids by comma ( like 1,3,45)
     Hint: You can negate option by -_option (like -_m) 
     Hint: Use module without subcommand (like monda tw) to see module specific help
     Hint: You can see default parameters using monda module -xh
    \n");
       parent::startup();
       Opts::readCfg(Array("global"));
       Opts::readOpts($this->params);
       Opts::helpOpts(); 
       Opts::showOpts();
       echo "\n";
       self::mexit();
    }
    
    public function renderDefault() {
        $this->Help();
    }

}

?>
