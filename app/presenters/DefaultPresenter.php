<?php


namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI,
        Nette\Utils\DateTime as DateTime;

class DefaultPresenter extends BasePresenter
{
    
    public function Help()
    {
        \App\Model\CliDebug::warn("
     Monitoring system data analysis
     
     You can use one of this commands:
     
     tw             - TimeWindow operations
     is             - ItemStat operations
     hs             - HostStat operations
     ic             - Item Correlation operations
     ec             - Event Correlation operations
     
     cron           - Combining all operations from cron
     
     html           - Create HTML output
     graphviz       - Create Graphiz output
     zabbix         - Zabbix output
     
     Hint: Date formats: @timestamp, YYYY_MM_DD_hhmm, YYYYMMDDhhmm, now, '-1 day'
     Hint: Divide more ids by comma ( like 1,3,45)
     Hint: You can negate option by -_option (like -_m) 
    \n");
       self::helpOpts();
       echo "\n";
    }
}

?>
