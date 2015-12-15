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
    private static $level;
    private static $logf;
    private static $writef;
    
    static function comparelevel($l1,$l2) {
        if (!is_numeric($l1)) {
            $l1=self::$levels[$l1];
        }
        if (!is_numeric($l2)) {
            $l2=self::$levels[$l2];
        }
        return($l1>=$l2);
    }
  
    public function __construct($level=false) {
        if (!$level) {
            if (getenv("MONDA_DEBUG")) {
                $level=getenv("MONDA_DEBUG");
            } else {
                if (isset(Monda::$debuglevel)) {
                    $level=Monda::$debuglevel;
                } else {
                    $level="info";
                }
            }
        }
        if (getenv("MONDA_LOG")) {
            self::$logf=fopen(getenv("MONDA_LOG")."/stderr.log","w");
        } else {
            self::$logf=fopen("php://stderr","w");
        }
        self::$writef=fopen("php://stdout","a");
        if (!array_key_exists($level, self::$levels)) {
            fprintf(self::$logf,"Unknown log level ".self::getLevel()."!\n");
        } else {
            self::$level=$level;
        }
    }
    
    public function getLevel() {
        return(self::$level);
    }
            
    static function log($message,$priority=Debugger::INFO) {
        if (self::comparelevel($priority,self::getLevel())) {
            fprintf(self::$logf,$message);
        }
     }
     
     static function write($message,$priority=Debugger::WARNING) {
        if (self::comparelevel($priority,self::getLevel())) {
            fprintf(self::$writef,$message);
        }
     }
     
     static function dbg($message) {
         self::log($message,Debugger::DEBUG);
     }
     
     static function progress($message) {
         fprintf(self::$logf,$message);
     }
     
     static function info($message) {
         self::log($message,Debugger::INFO);
     }
     
     static function warn($message) {
         self::log($message,Debugger::WARNING);
     }
     
     static function err($message) {
         self::log($message,Debugger::ERROR);
     }
     
     static function crit($message) {
         self::log($message,Debugger::CRITICAL);
     }
}
    
?>
