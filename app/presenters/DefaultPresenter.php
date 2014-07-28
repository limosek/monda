<?php


namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class DefaultPresenter extends BasePresenter
{
    
    public function Help()
    {
      
        echo "
     Monitoring system data analysis
     
     You can use one of this commands:
     
     tw             - TimeWindow operations
     is             - ItemStat operations
     hs             - HostStat operations
     ic             - ItemCorrelation operations
     loi            - Level Of interrest calculations
     cron           - Combining all operations from cron
     
     Hint: Date formats: @timestamp, YYYY_MM_DD_hhmm, YYYYMMDDhhmm, now, '-1 day'
     Hint: Divide more ids by comma ( like 1,3,45)
     Hint: You can negate option by -_option (like -_m) 
    \n";
       $this->helpOpts();
       echo "\n";
    }
}

?>
