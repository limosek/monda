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
    
    public $debuglevel;
    private $levels=Array(
        "debug" => 0,
        "info" => 1,
        "warning" =>2,
        "error" => 3,
        "critical" => 4
    );
    
    public function comparelevel($l1,$l2) {
        if (!is_numeric($l1)) {
            $l1=$this->levels[$l1];
        }
        if (!is_numeric($l2)) {
            $l2=$this->levels[$l2];
        }
        return($l1>=$l2);
    }
  
    public function __construct($level=Debugger::WARNING) {
        $this->debuglevel=$level;
        if (getenv("MONDA_DEBUG")) {
            $this->debuglevel=getenv("MONDA_DEBUG");
        }
        if (!array_key_exists($this->debuglevel, $this->levels)) {
            fprintf(STDERR,"Unknown log level $this->debuglevel! Using 'info'.\n");
            $this->debuglevel="info";
        }
    }
            
    function log($message,$priority=Debugger::INFO) {
        if ($this->comparelevel($priority,$this->debuglevel)) {
            fprintf(STDERR,$message);
        }
     }
     
     function write($message,$priority=Debugger::WARNING) {
        if ($this->comparelevel($priority,$this->debuglevel)) {
            fprintf(STDOUT,$message);
        }
     }
     
     function dbg($message) {
         $this->log($message,Debugger::DEBUG);
     }
     
     function info($message) {
         $this->log($message,Debugger::INFO);
     }
     
     function warn($message) {
         $this->log($message,Debugger::WARNING);
     }
     
     function err($message) {
         $this->log($message,Debugger::ERROR);
     }
     
     function crit($message) {
         $this->log($message,Debugger::CRITICAL);
     }
}
    
?>
