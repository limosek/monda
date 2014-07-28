<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    public $dbg;
    public $monda;
    public $opts;
    public $getopts=Array();
    public $exportdata;
    const TW_STEP=300;
    
    public function roundTime($tme) {
        return(round($tme/self::TW_STEP)*self::TW_STEP);
    }
    
    function mexit($code=0,$msg="") {
        if ($code==0) {
            $this->dbg->warn($msg);
        } else {
            $this->dbg->err($msg);
        }
        
        if (!getenv("MONDA_CHILD")) {
            $this->wait();
        }
        exit($code);
    }
    
    public function renderDefault() {
        $this->renderHelp();
    }
    
    public function renderHelp() {
        $this->Help();
        $this->mexit();
    }
    
    function timetoseconds($t) {
        if ($t[0] == "@") {
            return(substr($t, 1));
        } elseif (is_numeric($t)) {
            return($t);
        } elseif (preg_match("/(\d\d\d\d)\_(\d\d)\_(\d\d)\_(\d\d)(\d\d)/", $t, $r)) {
            $y = $r[1];
            $m = $r[2];
            $d = $r[3];
            $h = $r[4];
            $M = $r[5];
            $dte = New \DateTime("$y-$m-$d $h:$M");
            return(date_format($dte, "U"));
        } elseif (preg_match("/(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)/", $t, $r)) {
            $y = $r[1];
            $m = $r[2];
            $d = $r[3];
            $h = $r[4];
            $M = $r[5];
            $dte = New \DateTime("$y-$m-$d $h:$M");
            return(date_format($dte, "U"));
        } else {
            $dte = New \DateTime($t);
            return(date_format($dte, "U"));
        }
    }
    
    function startup() {
        global $container;
        
        if (!isset($this->opts)) {
            $this->opts=New \stdClass();
        }
        $this->opts=$this->getOpts($this->opts);
        //dump($this->opts);exit;
        if ($this->opts->help) {
            $this->forward($this->getName().":default");
        }
        parent::startup();
    }
  
    function parseOpt($obj,$key,$short,$long,$desc,$default=null,$defaulthelp=false) {
        if (array_key_exists($key,$this->getopts)) {
            throw New \Nette\Neon\Exception("Duplicated parameter (key=$key)!\n");
        }
        if (array_key_exists($short,$this->getopts)) {
            throw New \Nette\Neon\Exception("Duplicated parameter (short=$short)!\n");
        }
        if (array_key_exists($long,$this->getopts)) {
            throw New \Nette\Neon\Exception("Duplicated parameter (long=$long)!\n");
        }
        $this->getopts[$key]=Array(
            "short" => $short,
            "long" => $long,
            "description" => $desc,
            "default" => $default,
            "defaulthelp" => $defaulthelp
        );
        if (array_key_exists($short,$this->params)) {
            $obj->$key=$this->params[$short];
        } elseif (array_key_exists($long,$this->params)) {
            $obj->$key=$this->params[$long];
        } elseif (array_key_exists("_$short",$this->params)) {
            $obj->$key=!$this->params["_$short"];
        } elseif (array_key_exists("_$long",$this->params)) {
            $obj->$key=!$this->params["_$long"];
        } else {
            $obj->$key=$default;
        }
        if (is_object($this->dbg) && isset($obj->$key)) {
            $this->dbg->dbg("Setting option $long($desc) to ".  strtr(\Nette\Diagnostics\Debugger::dump($obj->$key,true),"\n"," ")."\n");
        }
        return($obj);
    }
    
    function helpOpts() {
        echo "[Common options]:\n";
        foreach ($this->getopts as $opt) {
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"]=$opt["default"];
            }
            $this->dbg->write(sprintf("-%s|--%s 'value': %s (default <%s>)\n",
                    $opt["short"],$opt["long"],     $opt["description"],    $opt["defaulthelp"]));
        }
    }
    
    function getOpts($ret) {
        //dump($this->params);exit;
        $ret=$this->parseOpt($ret,
                "help",
                "h","help",
                "Help. If used with module, help will be module specific",
                false
                );
        $ret=$this->parseOpt($ret,
                "debug",
                "D","debug",
                "Debug level (debug,info,warning,error,critical)",
                "info"
                );
        $this->dbg=New Model\CliDebug($ret->debug);
        $ret=$this->parseOpt($ret,
                "dry",
                "R","dry_run",
                "Only show what would be done. Do not touch db.",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "fork",
                "F","fork_level",
                "Fork level (how many processes to run simultanously)",
                false,
                "no fork"
                );
        $ret=$this->parseOpt($ret,
                "maxload",
                "Ml","max_load",
                "Run jobs only if OS loadavg is lower than value",
                10
                );
        $ret=$this->parseOpt($ret,
                "maxcpuwait",
                "Mw","max_cpuwait",
                "Run jobs only if CPU wait time lower than value[%%]",
                20
                );
       $ret=$this->parseOpt($ret,
                "maxbackends",
                "Mb","max_backends",
                "Run jobs only if there is less then value connected backends in DB",
                20
                );
        $ret=$this->parseOpt($ret,
                "noapi",
                "na","no_api",
                "Do not use Zabbix API. Use only cached values.",
                false,
                "API enabled"
                );
        $ret=$this->parseOpt($ret,
                "outputmode",
                "Om","output_mode",
                "Use this output mode {cli|csv|dump}",
                "cli",
                "cli"
                );
        $ret=$this->parseOpt($ret,
                "outputverb",
                "Ov","output_verbosity",
                "Use this output verbosity {brief,list,full}",
                "brief",
                "brief"
                );
        $ret=$this->parseOpt($ret,
                "zdsn",
                "Zd","zabbix_dsn",
                "Use this zabbix Database settings",
                "pgsql:host=127.0.0.1;port=5432;dbname=zabbix",
                "pgsql:host=127.0.0.1;port=5432;dbname=zabbix"
                );
        $ret=$this->parseOpt($ret,
                "zdbuser",
                "Zu","zabbix_db_user",
                "Use this zabbix Database user",
                "zabbix",
                "zabbix"
                );
        $ret=$this->parseOpt($ret,
                "zdbpw",
                "Zp","zabbix_db_pw",
                "Use this zabbix Database password",
                "",
                ""
                );
        $ret=$this->parseOpt($ret,
                "zid",
                "Zi","zabbix_id",
                "Use this zabbix server ID",
                "1",
                "1"
                );
        $ret=$this->parseOpt($ret,
                "mdsn",
                "Md","monda_dsn",
                "Use this monda Database settings",
                "pgsql:host=127.0.0.1;port=5432;dbname=monda",
                "pgsql:host=127.0.0.1;port=5432;dbname=monda"
                );
        $ret=$this->parseOpt($ret,
                "mdbuser",
                "Mu","monda_db_user",
                "Use this monda Database user",
                "monda",
                "monda"
                );
        $ret=$this->parseOpt($ret,
                "mdbpw",
                "Mp","monda_db_pw",
                "Use this monda Database password",
                "",
                ""
                );
        $ret=$this->parseOpt($ret,
                "zapiurl",
                "Za","zabbix_api_url",
                "Use this zabbix API url",
                "http://localhost/api_jsonrpc2.php",
                "http://localhost/api_jsonrpc2.php"
                );
        $ret=$this->parseOpt($ret,
                "zapiuser",
                "Zau","zabbix_api_user",
                "Use this zabbix API user",
                "monda",
                "monda"
                );
        $ret=$this->parseOpt($ret,
                "zapipw",
                "Zap","zabbix_api_pw",
                "Use this zabbix API password",
                "",
                ""
                );
        return($ret);
    }
    
    function wait() {
        if (function_exists('pcntl_wait')) {
            $code=0;
            return(pcntl_wait($code,WNOHANG));
        }
    }
    
    
    
    function renderCli() {

       foreach ((array) $this->exportdata as $id=>$row) {
           echo "#Row $id:\n";
            foreach ($row as $r=>$v) {
                echo "$r='$v'\n";
            }
            echo "\n\n";
        }
        $this->mexit();
    }
    
    function renderCsv() {

        $i = 0;
        foreach ((array) $this->exportdata as $id => $row) {
            if ($i == 0) {
                foreach ($row as $r => $v) {
                    echo "$r;";
                }
                echo "\n";
            }
            foreach ($row as $r => $v) {
                echo "$v;";
            }
            echo "\n";
            $i++;
        }
        $this->mexit();
    }
    
    function renderDump() {
        var_export($this->exportdata);
        $this->mexit();
    }
    
    function renderShow($var) {
        $this->exportdata=$var;
        switch ($this->opts->outputmode) {
            case "cli":
                $this->renderCli();
                break;
            case "csv":
                $this->renderCsv();
                break;
            case "dump":
                $this->renderDump();
                break;
            default:
                throw New Nette\Neon\Exception("Unknown output mode!\n");
        }
    }
    
}
