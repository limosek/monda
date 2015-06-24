<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,
        App\Model\Options,
        Tracy\Debugger,
        \App\Model\CliLogger,
        Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{ 
    public $defaulthints = Array(
        "General syntax: monda.php module:action [common options]",
        "Use 'monda.php -xh' for basic extended help",
        "Use 'monda.php module' for module specific help",
        "Use 'monda.php module -xh' for module specific extended help",
        "Date formats: @timestamp, YYYY_MM_DD_hhmm, YYYYMMDDhhmm, now, '-1 day'",
        "Divide more values for option by comma ( like 1,3,45)",
        "You can negate option by -_option (like -_m)",
        "You can set options in file ~/.mondarc (for cli) or app/config/monda.rc (for web)",
        "You can set option by environment (MONDA_option). Use _ instead of . (like MONDA_zabbix_apipw"
    );
    
    function mexit($code=0,$msg="") {
        if ($code==0) {
            Debugger::log($msg,Debugger::INFO);
        } else {
            Debugger::log($msg,Debugger::ERROR);
        }
        if (PHP_SAPI != "cli") {
            if ($code!=0) {
                throw New Exception("Error #$code: $msg");
            } else {
                $this->terminate();
            }
        } else {
            exit($code);
        }
    }

    function startup() {
        parent::startup();
        if (isset($this->module)) {
            Options::extend($this->module,$this->moduleparams);
            Options::read($this->params);
        }
        if (array_key_exists("conffile", $this->params)) {
            Options::read(Array("conffile" => $this->params["conffile"]));
        } else {
            if (getenv("MONDARC")) {
                Options::readFile(getenv("MONDARC"));
            }
        }
        Options::readEnv();
        Options::read($this->params);
        if (Options::get("configinfo")) {
            foreach (Options::get() as $key=>$val) {
                $arr=Options::info($key);
                if ($arr["default"]) {
                    CliLogger::log(sprintf("Option %s => Value:'%s', set from:defaults\n",$key,$arr["value"]),Debugger::ERROR);
                } else {
                    CliLogger::log(sprintf("Option %s => Value:'%s', set from:%s\n",$key,$arr["value"],$arr["setfrom"]),Debugger::ERROR);
                }
            }
            self::mexit();
        }
        if ($this->name==$this->action) {
            $this->Help(Options::get("xhelp"));
        }
    }
    
    function renderDefault() {
        $this->Help(Options::get("xhelp"));
    }
    
    public function helpModule() {
        CliLogger::log("\n=== $this->modulehelp ===\n",Debugger::ERROR);
    }
    
    public function helpActions() {
        CliLogger::log("Actions:\n",Debugger::ERROR);
        if (isset($this->moduleactions)) {
            foreach ($this->moduleactions as $a=>$v) {
                CliLogger::log("$a - $v\n",Debugger::ERROR);
            }
        }
        CliLogger::log("\n",Debugger::ERROR);
    }
    
    public function helpHints() {
        CliLogger::log("\n",Debugger::ERROR);
        if (isset($this->defaulthints)) {
            foreach ($this->defaulthints as $a=>$v) {
                CliLogger::log("Hint: $v\n",Debugger::ERROR);
            }
        }
        if (isset($this->modulehints)) {
            foreach ($this->modulehints as $a=>$v) {
                CliLogger::log("Hint: $v\n",Debugger::ERROR);
            }
        }
        CliLogger::log("\n",Debugger::ERROR);
    }
    
    public function helpOptions($module) {
        Options::help($module,1);
        CliLogger::log("\n",Debugger::ERROR);
    }
    
    public function Help($extended=false) {
        $this->helpModule();
        $this->helpActions();
        $this->helpHints();
        if ($this->name<>'Default') {
            if (Options::get("xhelp")) $this->helpOptions($this->name);
        } else {
            if (Options::get("xhelp")) $this->helpOptions(false);
        }
        self::mexit();
    }
       
}
