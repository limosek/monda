<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,
        \Tracy\Debugger,
        Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    static $getopts=Array();
    public $exportdata;
    static $parameters;
    
    const TW_STEP=300;

    static function roundTime($tme) {
        return(round($tme/self::TW_STEP)*self::TW_STEP);
    }
    
    function mexit($code=0,$msg="") {
        if ($code==0) {
            Model\CliDebug::warn($msg);
        } else {
            Model\CliDebug::err($msg);
        }
        if (!getenv("MONDA_CHILD")) {
            $this->wait();
        }
        if (!getenv("MONDA_CLI")) {
            if ($code!=0) {
                throw New Exception("Error #$code: $msg");
            } else {
            $this->terminate();
            }
        } else {
            exit($code);
        }
    }
    
    public function renderDefault() {
        $this->Help();
        self::mexit();
    }    
    public function __call($name, $args) {
        parent::__call($name, $args);
    }
    
    static function timetoseconds($t) {
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
            $dte = New DateTime("$y-$m-$d $h:$M".date("P"));
            return(date_format($dte, "U"));
        } elseif (preg_match("/(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)/", $t, $r)) {
            $y = $r[1];
            $m = $r[2];
            $d = $r[3];
            $h = $r[4];
            $M = $r[5];
            $dte = New DateTime("$y-$m-$d $h:$M".date("P"));
            return(date_format($dte, "U"));
        } else {
            $dte = New DateTime($t);
            return(date_format($dte, "U"));
        }
    }
    
    function startup() {
        global $container;
        
        if (!isset(Model\Monda::$opts)) {
            Model\Monda::$opts=New \stdClass();
        }
        Model\Monda::$opts=$this->getOpts(Model\Monda::$opts);
        if (Model\Monda::$opts->help) {
            Model\Monda::$opts->zapi=false;
            $this->forward($this->getName().":default");
        }
        parent::startup();
        
        return($this);
    }
    
    function __construct() {
        global $container;
        
        $c = $container;
        Model\Monda::$apicache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_APICACHEDIR")));
        Model\Monda::$sqlcache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_SQLCACHEDIR")));
        Model\Monda::$cache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_CACHEDIR")));
    }
  
    static function parseOpt($obj,$key,$short,$long,$desc,$default=null,$defaulthelp=false,$choices=false,$params=false) {
        global $container;
        
        echo "\t'$key' => Array (
                '$short',\t\t'$long',\t\t'$default'\t\t,\t'$defaulthelp'\t\t,'$choices',
                    \t'$desc'
                ),\n\n";
        if (!$params) {
            if (count($_GET)>0) {
                $params=$_GET;
            } else {
                //dump($container->getService("application")->getRequests());
                $params=$container->getService("application")->getRequests()[0]->getParameters();
            }
        }
        self::$getopts[$key]=Array(
            "short" => $short,
            "long" => $long,
            "description" => $desc,
            "default" => $default,
            "defaulthelp" => $defaulthelp,
            "choices" => $choices
        );
        if ($short && array_key_exists($short,$params)) {
            $value=stripslashes($params[$short]);
        } elseif (array_key_exists($long,$params)) {
            $value=stripslashes($params[$long]);
        } elseif (array_key_exists("_$short",$params)) {
            $value=!$params["_$short"];
        } elseif (array_key_exists("_$long",$params)) {
            $value=!$params["_$long"];
        } else {
            $value=$default;
            $obj->defaults[]=$key;
        }
        $obj->$key=$value;
        if ($choices) {
            if (array_search($value,$choices)===false) {
                self::mexit(14,sprintf("Bad option %s for parameter %s(%s). Possible values: {%s}\n",$value,$short,$long,join($choices,"|")));
            }
        }
        if (isset($obj->$key)) {
            Model\CliDebug::dbg("Setting option $long($desc) to ".  strtr(Debugger::dump($obj->$key,true),"\n"," ")."\n");
        }
        return($obj);
    }
    
    function setOpt($opt,$value) {
        $this->params[$opt]=$value;
    }
    
    function isOptDefault($key) {
        if (array_search($key,Model\Monda::$opts->defaults)===false) {
            return(false);
        } else {
            return(true);
        }
    }
    
    public function helpOpts() {
        Model\CliDebug::warn(sprintf("[Common options for %s]:\n",$this->getName()));
        $opts=self::$getopts;
        if (!Model\Monda::$opts->xhelp) {
            return;
        }
        foreach ($opts as $key=>$opt) {
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"]=$opt["default"];
            }
            if (array_key_exists("choices",$opt) && is_array($opt["choices"])) {
                $choicesstr="Choices: {".join("|",$opt["choices"])."}\n";
            } else {
                $choicesstr="";
            }
            if (self::isOptDefault($key)) {
                $avalue="Default";
            } else {
                if (is_array(Model\Monda::$opts->$key)) {
                    $avalue=join(",",Model\Monda::$opts->$key);
                } elseif (is_bool(Model\Monda::$opts->$key)) {
                    $avalue=sprintf("%b",Model\Monda::$opts->$key);    
                } else {
                    $avalue=Model\Monda::$opts->$key;
                }
            }
            Model\CliDebug::warn(sprintf("-%s|--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n",
                    $opt["short"],$opt["long"],     $opt["description"],    $opt["defaulthelp"], $avalue, $choicesstr));
        }
    }
    
     static function getOpts($ret) {
        $ret=self::parseOpt($ret,
                "help",
                "h","help",
                "Help. If used with module, help will be module specific",
                false
                );
        $ret=self::parseOpt($ret,
                "xhelp",
                "xh","advanced_help",
                "Advanced help. If used with module, help will be module specific",
                false
                );
        $ret=self::parseOpt($ret,
                "debug",
                "D","debug",
                "Debug level (debug,info,warning,error,critical)",
                "info"
                );
        New Model\CliDebug($ret->debug);
        $ret=self::parseOpt($ret,
                "progress",
                "P","progress",
                "Progress informations on stderr",
                false
                );
        $ret=self::parseOpt($ret,
                "configinfo",
                "C","config-info",
                "Configuration information",
                false
                );
        $ret=self::parseOpt($ret,
                "dry",
                "R","dry_run",
                "Only show what would be done. Do not touch db.",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "fork",
                "F","fork_level",
                "Fork level (how many processes to run simultanously)",
                false,
                "no fork"
                );
        $ret=self::parseOpt($ret,
                "maxload",
                "Ml","max_load",
                "Run jobs only if OS loadavg is lower than value",
                10
                );
        $ret=self::parseOpt($ret,
                "maxcpuwait",
                "Mw","max_cpuwait",
                "Run jobs only if CPU wait time lower than value[%%]",
                20
                );
       /* $ret=self::parseOpt($ret,
                "maxbackends",
                "Mb","max_backends",
                "Run jobs only if there is less then value connected backends in DB",
                20
                ); */
        $ret=self::parseOpt($ret,
                "zapi",
                "za","zabbix_api",
                "Use Zabbix API to retrieve objects. If this is false, cache is used. If object is not in cache, return empty values.",
                false,
                "API disabled"
                );
        $ret=self::parseOpt($ret,
                "outputmode",
                "Om","output_mode",
                "Use this output mode {cli|csv|dump}",
                "cli",
                "cli"
                );
        $ret=self::parseOpt($ret,
                "outputverb",
                "Ov","output_verbosity",
                "Use this output verbosity {id,expanded}",
                "ids",
                "ids"
                );
        $ret=self::parseOpt($ret,
                "zdsn",
                "Zd","zabbix_dsn",
                "Use this zabbix Database settings",
                "pgsql:host=127.0.0.1;port=5432;dbname=zabbix",
                "pgsql:host=127.0.0.1;port=5432;dbname=zabbix"
                );
        $ret=self::parseOpt($ret,
                "zdbuser",
                "Zu","zabbix_db_user",
                "Use this zabbix Database user",
                "zabbix",
                "zabbix"
                );
        $ret=self::parseOpt($ret,
                "zdbpw",
                "Zp","zabbix_db_pw",
                "Use this zabbix Database password",
                "",
                ""
                );
        $ret=self::parseOpt($ret,
                "zid",
                "Zi","zabbix_id",
                "Use this zabbix server ID",
                "1",
                "1"
                );
        $ret=self::parseOpt($ret,
                "mdsn",
                "Md","monda_dsn",
                "Use this monda Database settings",
                "pgsql:host=127.0.0.1;port=5432;dbname=monda",
                "pgsql:host=127.0.0.1;port=5432;dbname=monda"
                );
        $ret=self::parseOpt($ret,
                "mdbuser",
                "Mu","monda_db_user",
                "Use this monda Database user",
                "monda",
                "monda"
                );
        $ret=self::parseOpt($ret,
                "mdbpw",
                "Mp","monda_db_pw",
                "Use this monda Database password",
                "M0nda",
                "M0nda"
                );
        $ret=self::parseOpt($ret,
                "zaburl",
                "ZU","zabbix_url",
                "Base of zabbix urls",
                "http://localhost/zabbix",
                "http://localhost/zabbix"
                );
        $ret=self::parseOpt($ret,
                "zapiurl",
                "Za","zabbix_api_url",
                "Use this zabbix API url",
                $ret->zaburl."/api_jsonrpc.php",
                $ret->zaburl."/api_jsonrpc.php"
                );
        $ret=self::parseOpt($ret,
                "zapiuser",
                "Zau","zabbix_api_user",
                "Use this zabbix API user",
                "monda",
                "monda"
                );
        $ret=self::parseOpt($ret,
                "zapipw",
                "Zap","zabbix_api_pw",
                "Use this zabbix API password",
                "",
                ""
                );
        $ret=self::parseOpt($ret,
                "csvdelim",
                false,"csv_delimiter",
                "Use this delimiter for CSV output",
                ";",
                ";"
                );
        $ret=self::parseOpt($ret,
                "csvenc",
                false,"csv_enclosure",
                "Use this enclosure for CSV output",
                '"',
                '"'
                );
        $ret=self::parseOpt($ret,
                "zabbix_history_table",
                "Zht","zabbix_history_table",
                "Zabbix history table to work on",
                "history",
                "history"
                );
        $ret=self::parseOpt($ret,
                "zabbix_history_uint_table",
                "Zhut","zabbix_history_uint_table",
                "Zabbix history_uint table to work on",
                "history_uint",
                "history_uint"
                );
        $ret=self::parseOpt($ret,
                "apicacheexpire",
                "Ace","api_cache_expire",
                "Maximum time to cache api requests. Use 0 to not cache.",
                "24 hours",
                "24 hours"
                );
        $ret=self::parseOpt($ret,
                "sqlcacheexpire",
                "Sce","sql_cache_expire",
                "Maximum time to cache sql requests. Use 0 to not cache.",
                "1 hour",
                "1 hour"
                );
        $ret=self::parseOpt($ret,
                "nocache",
                "nc","nocache",
                "Disable both SQL and API cache",
                false,
                "no"
                );
        if (isset($ret->nocache)) {
            $ret->sql_cache_expire=0;
            $ret->api_cache_expire=0;
        }
        $ret=self::parseOpt($ret,
                "sow",
                "sw","sow",
                "Star day of week",
                "Monday",
                "Monday"
                );
        return($ret);
    }
    
    function wait() {
        if (function_exists('pcntl_wait')) {
            $code=0;
            return(pcntl_wait($code,WNOHANG));
        }
    }
    
    function beforeRender() {
        if (Model\Monda::$opts->configinfo) {
            dump(Model\Monda::$opts);
            self::mexit();
        }
    }
    
    function renderCli() {
        global $container;
        $httpResponse = $container->getByType('Nette\Http\Response');
        $httpResponse->setContentType('text/csv', 'UTF-8');
        
       foreach ((array) $this->exportdata as $id=>$row) {
           echo "#Row $id (size ".count($row).")\n";
            foreach ($row as $r=>$v) {
                echo "$r='$v'\n";
            }
            echo "\n\n";
        }
        self::mexit();
    }
    
    function renderCsv() {
        global $container;
        $httpResponse = $container->getByType('Nette\Http\Response');
        $httpResponse->setContentType('text/csv', 'UTF-8');
        
        $opts=Model\Monda::$opts;
        $i = 0;
        
        foreach ((array) $this->exportdata as $id => $row) {
            if ($i == 0) {
                foreach ($row as $r => $v) {
                    echo sprintf('%s%s%s;',$opts->csvenc,$r,$opts->csvenc);
                }
                echo "\n";
            }
            $cnt=count($row);
            $j=1;
            foreach ($row as $r => $v) {
                if (is_object($v)) { 
                    if (get_class($v)=="Nette\Utils\DateTime") {
                        $v=$v->format("c");
                    }
                }
                if ($j!=$cnt) {
                    echo sprintf('%s%s%s%s',$opts->csvenc,$v,$opts->csvenc,$opts->csvdelim);
                } else {
                    echo sprintf('%s%s%s',$opts->csvenc,$v,$opts->csvenc);
                }
                $j++;
            }
            echo "\n";
            $i++;
        }
        self::mexit();
    }
    
    function renderDump() {
        var_export($this->exportdata);
        self::mexit();
    }
    
    function renderShow($var) {
        $this->exportdata=$var;
        switch (Model\Monda::$opts->outputmode) {
            case "cli":
                self::renderCli();
                break;
            case "csv":
                self::renderCsv();
                break;
            case "dump":
                self::renderDump();
                break;
            default:
                throw New Nette\Neon\Exception("Unknown output mode!\n");
        }
    }
    
}
