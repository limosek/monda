<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class CliDebug {

    private static $levels=Array(
        "debug" => 0,
        "info" => 1,
        "warning" =>2,
        "error" => 3,
        "critical" => 4
    );
    
    public function comparelevel($l1,$l2) {
        if (!is_numeric($l1)) {
            $l1=self::$levels[$l1];
        }
        if (!is_numeric($l2)) {
            $l2=self::$levels[$l2];
        }
        return($l1>=$l2);
    }
  
    public function __construct($level=Debugger::WARNING) {
        if (!array_key_exists(self::getLevel(), self::$levels)) {
            fprintf(STDERR,"Unknown log level ".self::getLevel()."!\n");
        }
    }
    
    public function getLevel() {
        if (getenv("MONDA_DEBUG")) {
            $l=getenv("MONDA_DEBUG");
        } else {
            if (isset($this->debuglevel)) {
                $l=$this->debuglevel;
            } else {
                $l="warning";
            }
        }
        if (!array_key_exists($l, self::$levels)) {
            fprintf(STDERR,"Unknown log level ".$l."!\n");
            $l="info";
        }
        return($l);
    }
            
    function log($message,$priority=Debugger::INFO) {
        if (self::comparelevel($priority,self::getLevel())) {
            fprintf(STDERR,$message);
        }
     }
     
     function write($message,$priority=Debugger::WARNING) {
        if (self::comparelevel($priority,self::getLevel())) {
            fprintf(STDOUT,$message);
        }
     }
     
     function dbg($message) {
         self::log($message,Debugger::DEBUG);
     }
     
     function info($message) {
         self::log($message,Debugger::INFO);
     }
     
     function warn($message) {
         self::log($message,Debugger::WARNING);
     }
     
     function err($message) {
         self::log($message,Debugger::ERROR);
     }
     
     function crit($message) {
         self::log($message,Debugger::CRITICAL);
     }
}
    
?>
