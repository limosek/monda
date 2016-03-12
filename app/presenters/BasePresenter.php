<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,
        Tracy\Debugger,
        App\Model\CliDebug,
        Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    public $getopts=Array();
    public $exportdata;
    const TW_STEP=300;
    public $cache; // Cache
    public $apicache; // Cache for zabbix api
    public $sqlcache;
    public $api;   // ZabbixApi class
    public $zq;    // Zabbix query link id
    public $mq;    // Monda query link id
    public $dbg;    // Cli debugger
    public $zabbix_url;
    public $zabbix_user;
    public $zabbix_pw;
    public $zabbix_db_type;
    public $stats=Array();
    public $lastns=false;
    public $opts;
    public $cpustats;
    public $cpustatsstamp;
    public $childpids=Array();
    public $childs;
    public $debuglevel="warning";
    public $lastsql;
    public $jobstats;

    public function roundTime($tme) {
        return(round($tme/self::TW_STEP)*self::TW_STEP);
    }
    
    function mexit($code = 0, $msg = "") {
        if (!$msg) {
            if (array_key_exists("exception", $this->params)) {
                $msg = $this->params["exception"]->getMessage() . "\n";
                $code = $this->params["exception"]->getCode();
                CliDebug::dbg("Used params: " . print_r($this->params, true));
                echo Debugger::getBlueScreen()->render($this->params["exception"]);
                echo Debugger::getBar()->render();
            }
        }
        if ($code == 0) {
            CliDebug::warn($msg);
        } else {
            CliDebug::err($msg);
        }
        if (!getenv("MONDA_CHILD")) {
            $this->wait();
        }
        if (!getenv("MONDA_CLI")) {
            //throw New Exception("Error #$code: $msg");
        } else {
            exit($code);
        }
    }
    
    public function __call($name, $args) {
        parent::__call($name, $args);
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
        
        if (!isset($this->opts)) {
            $this->opts=New \stdClass();
        }
        $this->opts=$this->getOpts($this->opts);
        if ($this->opts->help || $this->opts->xhelp) {
            $this->opts->zapi=false;
        }
        $this->opts=self::readCfg($this->opts);
        parent::startup();
        return($this);
    }
    
    function readCfg($obj,$contexts = false) {
        global $ctxs;
        
        if (!is_array($ctxs)) {
            $ctxs=Array();
        }
        if (!$contexts) {
            $contexts = Array(
                "global",
                $this->name,
                $this->name.":".$this->action);
        }
        if ($this->opts->zalias) {
            $contexts[]="zabbix-".$this->opts->zalias;
        }
        if (file_exists(getenv("MONDARC"))) {
            $fopts = parse_ini_file(getenv("MONDARC"), true);
            $foptions = Array();
            foreach ($contexts as $context) {
                CliDebug::dbg("Want to read INI context $context.\n");
                if (array_key_exists($context,$fopts) && !array_key_exists($context,$ctxs)) {
                    $foptions = array_merge($foptions, $fopts[$context]);
                    CliDebug::info("Got config from INI context $context.\n");
                    $ctxs[$context]=1;
                }
            }
            foreach ($foptions as $opt=>$value) {
                $obj=self::iniSetOpt($obj,$opt,$value);
            }
        }
        return($obj);
    }

    function __construct() {
        $this->dbg=New CliDebug();
        $this->apicache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_APICACHEDIR")));
        $this->sqlcache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_SQLCACHEDIR")));
        $this->cache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_CACHEDIR")));
    }
  
    function parseOpt($obj, $key, $short, $long, $desc, $default = null, $defaulthelp = false, $choices = false, $params = false) {

        if (isset($obj->$key)) {
            return($obj); // Already set before
        }
        if (!$params) {
            if (is_array($this->params)) {
                $params = $this->params;
                if (array_key_exists("exception", $this->params)) {
                    $params=Array();
                }
            }
        }
        $this->getopts[$key] = Array(
            "short" => $short,
            "long" => $long,
            "description" => $desc,
            "default" => $default,
            "defaulthelp" => $defaulthelp,
            "choices" => $choices
        );
        if ($short && array_key_exists($short, $params)) {
            $value = stripslashes($params[$short]);
        } elseif (array_key_exists($long, $params)) {
            $value = stripslashes($params[$long]);
        } elseif (array_key_exists("_$short", $params)) {
            $value = !$params["_$short"];
        } elseif (array_key_exists("_$long", $params)) {
            $value = !$params["_$long"];
        } else {
            $value = $default;
            $obj->defaults[] = $key;
        }
        $obj->$key = $value;
        if ($choices) {
            if (array_search($value, $choices) === false) {
                self::mexit(14, sprintf("Bad option %s for parameter %s(%s). Possible values: {%s}\n", $value, $short, $long, join($choices, "|")));
            }
        }
        if (is_object($this->dbg) && isset($obj->$key)) {
            CliDebug::info("Setting option $long($desc) to " . strtr(Debugger::dump($obj->$key, true), "\n", " ") . "\n");
        }
        return($obj);
    }
    
    function iniSetOpt($obj,$key,$value) {
        $set=false;
        foreach ($this->getopts as $k=>$v) {
            if ($v["long"]==$key) {
                if (true) {
                    $set=true;
                    $obj->$k=$value;
                    CliDebug::dbg("Setting INI option $key to " . strtr(Debugger::dump($value, true), "\n", " ") . "\n");
                }
            }
        }
        if (!$set) {
            CliDebug::warn("INI option $key unknown!\n"); 
        }
        return($obj);
    }
    
    function setOpt($opt,$value) {
        $this->params[$opt]=$value;
    }
    
    function isOptDefault($key) {
        if (array_search($key,$this->opts->defaults)===false) {
            return(false);
        } else {
            return(true);
        }
    }
    
    function helpOpts() {
        CliDebug::warn(sprintf("[Common options for %s]:\n", $this->getName()));
        $opts = self::getOpts($this->opts);
        if (!$opts->xhelp && !$opts->help) {
            if (!$opts->help) {
                CliDebug::warn("Use -h to get more info about parameters.\n");
            }
            return;
        }
        foreach ($this->getopts as $key => $opt) {
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"] = $opt["default"];
            }
            if (array_key_exists("choices", $opt) && is_array($opt["choices"])) {
                $choicesstr = "Choices: {" . join("|", $opt["choices"]) . "}\n";
            } else {
                $choicesstr = "";
            }
            if (is_array($opts->$key)) {
                $avalue = join(",", $opts->$key);
            } elseif (is_bool($opts->$key)) {
                $avalue = sprintf("%b", $opts->$key);
            } else {
                $avalue = $opts->$key;
            }
            if ($opt["short"]) {
                $short = "-" . $opt["short"] . "|";
            } else {
                $short = "";
            }
            if ($opts->xhelp) {
                CliDebug::warn(sprintf("%s--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n", $short, $opt["long"], $opt["description"], $opt["defaulthelp"], $avalue, $choicesstr));
            } else {
                CliDebug::warn(sprintf("%s--%s %s\n", $short, $opt["long"], $opt["description"]));
            }
        }
        self::showOpts();
        if (!$opts->xhelp) {
             CliDebug::warn("Use -xh to get more info about parameters.\n");
        }
    }
    
    function showOpts() {
        CliDebug::warn(sprintf("[Options read from INI, environment and args]:\n", $this->getName()));
        $opts = self::getOpts($this->opts);
        
        foreach ($this->getopts as $key => $opt) {
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"] = $opt["default"];
            }
            CliDebug::warn(sprintf("%s--%s 'value':",$opts->$opt));
        }
    }

    function zabbixGraphUrl1($itemids, $start, $seconds) {
        if ($itemids) {
            $itemidsstr = "";
            foreach ($itemids as $i) {
                $itemidsstr.="itemids[$i]=$i&";
            }
        } else {
            $itemidsstr = "";
        }
        $url=sprintf("%s/history.php?", $this->opts->zaburl) . sprintf("action=batchgraph&%s&graphtype=0&period=%d&stime=%d", $itemidsstr, $seconds, $start);
        return($url);
    }
    
    function zabbixGraphUrl2($itemids, $start, $seconds) {
        if ($itemids) {
            $itemidsstr = "";
            $j=0;
            foreach ($itemids as $i) {
                $itemidsstr.="itemids[$j]=$i&";
                $j++;
            }
        } else {
            $itemidsstr = "";
        }
        $url=sprintf("%s/chart.php?", $this->opts->zaburl) . sprintf("period=%d&stime=%s&%s&type=0&batch=1&updateProfile=0&profileIdx=&profileIdx2=&width=1024", $seconds, date("YmdHis",$start), $itemidsstr);
        return($url);
    }

    function getOpts($ret) {
        global $argv;
        
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
                "ctest",
                "C","config_test",
                "Show config options read from INI, env and args",
                false
                );
        $ret=self::parseOpt($ret,
                "debug",
                "D","debug",
                "Debug level (debug,info,warning,error,critical)",
                "warning"
                );
        if (getenv("MONDA_CLI")) {
            foreach ($argv as $i => $a) {
                if (!strcmp($a, "--help") ||
                        !strcmp($a, "-h")
                ) {
                    $ret->help = true;
                }
                if (!strcmp($a, "-xh") ||
                        !strcmp($a, "--xhelp")
                ) {
                    $ret->xhelp = true;
                }
                if (!strcmp($a, "-D") ||
                        !strcmp($a, "--debug")
                ) {
                    $ret->debug = $argv[$i + 1];
                }
            }
        }
        $this->dbg=New CliDebug($ret->debug);
        if ($ret->debug=="debug") {
            Debugger::enable(Debugger::DEVELOPMENT);
        } else {
            Debugger::enable(Debugger::PRODUCTION);
        }
        $ret=self::parseOpt($ret,
                "dry",
                "R","dry_run",
                "Only show what would be done. Do not touch db.",
                false,
                "no"
                );
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
                "csvsep",
                false,"csv_separator",
                "Use this CSV separator",
                ";",
                ";"
                );
        $ret->csvsep=htmlspecialchars_decode($ret->csvsep);
        $ret=self::parseOpt($ret,
                "csvfield",
                false,"csv_field_enclosure",
                "Use this CSV enclosure",
                '"',
                '"'
                );
        $ret->csvfield=htmlspecialchars_decode($ret->csvfield);
        $ret=self::parseOpt($ret,
                "csvheader",
                false,"csv_header",
                "Use CSV header",
                true,
                true
                );
        $ret=self::parseOpt($ret,
                "csvfields",
                false,"csv_fields",
                "Output only this fields",
                false,
                false
                );
        if ($ret->csvfields) $ret->csvfields=array_flip(preg_split("/,/",$ret->csvfields));
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
                "zalias",
                false,"zabbix_alias",
                "Use this zabbix server alias",
                getenv("MONDA_ZABBIX_ALIAS"),
                "\${MONDA_ZABBIX_ALIAS}"
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
        $ret=$this->parseOpt($ret,
                "max_rows",
                "Im","max_rows",
                "Maximum number of rows to get (LIMIT for SELECT)",
                300,
                "300"
                );
        $ret=self::parseOpt($ret,
                "minloi",
                false,"min_loi",
                "Select only objects which have loi bbigger than this",
                0,
                0
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
    
    function renderCli() {

       foreach ((array) $this->exportdata as $id=>$row) {
           echo "#Row $id:\n";
            foreach ($row as $r=>$v) {
                echo "$r='$v'\n";
            }
            echo "\n\n";
        }
        self::mexit();
    }
    
    function renderCsv() {
        $i = 0;
        foreach ((array) $this->exportdata as $id => $row) {
            if ($i == 0 && $this->opts->csvheader) {
                foreach ($row as $r => $v) {
                    echo sprintf('%s%s%s%s',$this->opts->csvfield,$r,$this->opts->csvfield,$this->opts->csvsep);
                }
                echo "\n";
            }
            $col=1;
            foreach ($row as $r => $v) {
                if (is_object($v)) { 
                    if (get_class($v)=="Nette\Utils\DateTime") {
                        $v=$v->format("c");
                    }
                }
                $print=true;
                if (is_array($this->opts->csvfields)) {
                    if (array_key_exists($col,$this->opts->csvfields) ||array_key_exists($r,$this->opts->csvfields)) {
                        $print=true;
                    } else {
                        $print=false;
                    }
                }
                if ($print) echo sprintf('%s%s%s%s',$this->opts->csvfield,$v,$this->opts->csvfield,$this->opts->csvsep);
                $col++;
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
    
    function renderShow() {
        switch ($this->opts->outputmode) {
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
