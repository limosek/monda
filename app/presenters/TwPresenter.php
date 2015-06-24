<?php

namespace App\Presenters;

use \Exception,Nette,
        \Tracy\Debugger,
	App\Model,
        App\Model\CliLogger,
        App\Model\Tw,
        App\Model\Options,
        Nette\Utils\DateTime as DateTime;

class TwPresenter extends BasePresenter
{
    
    public function renderTw() {
        self::Help();
        self::mexit();
    }
    
    static function Help() {
       Debugger::log("
     Time Window operations
     
     tw:create [common opts]
        Create window(s) for specified period and length

     tw:delete [common opts]
        Remove windows and dependent data from this range
     
     tw:empty [common opts]
        Empty windows data but leave windows created
        
     tw:show
        Show informations about timewindows in db
        
     tw:stats
        Show statistics about timewindows in db
    
    tw:zstats
        Show statistics about zabbix data at timewindows
    
    tw:modify
        Modify or rename window(s)
        
     tw:loi
        Recompute Level of Interest for windows
     
     Date formats: @timestamp, YYYYMMDDhhmm, now, '1 day ago', '00:00 1 day ago'
     TimeWindow formats: Date_format/length, Date_format-Date_format/length, id
     If no start and end date given, all data will be affected.
     
    [common opts]
     \n",Debugger::ERROR);
       Options::help();
       exit;
    }
   
}