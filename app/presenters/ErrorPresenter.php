<?php
namespace App\Model;

use Nette,
        Tracy\Debugger;

/**
 * ItemStat global class
 */
class Options extends Nette\Object {
    
   private static $opts;
   private static $getopts=Array();
   private static $params;
   private static $defaults;
   private static $confparams = Array(
        //key,               short,  long,              default,    defaulthelp,    choices.
        //description
       'debug' => Array(
            'D', 'debug', 'warning', '', '',
            'Debug level (debug,info,warning,error,critical)'
        ),
       'logfile' => Array(
            'Lf', 'logfile', 'php://stderr', '', '',
            'Log file'
        ),
        'help' => Array(
            'h', 'help', '', '', '',
            'Help. If used with module, help will be module specific'
        ),
        'xhelp' => Array(
            'xh', 'advanced_help', '', '', '',
            'Advanced help for all options'
        ),
        'progress' => Array(
            'P', 'progress', '', '', '',
            'Progress informations on stderr'
        ),
        'configinfo' => Array(
            'C', 'config_info', '', '', '',
            'Configuration information'
        ),
        'dry' => Array(
            'R', 'dry_run', '', 'no', '',
            'Only show what would be done. Do not touch db.'
        ),
        'fork' => Array(
            'F', 'fork_level', '', 'no fork', '',
            'Fork level (how many processes to run simultanously)'
        ),
        'maxload' => Array(
            'Ml', 'max_load', '10', '', '',
            'Run jobs only if OS loadavg is lower than value'
        ),
        'maxcpuwait' => Array(
            'Mw', 'max_cpuwait', '20', '', '',
            'Run jobs only if CPU wait time lower than value[%%]'
        ),
        'zapi' => Array(
            'za', 'zabbix_api', '', 'API disabled', '',
            'Use Zabbix API to retrieve objects. If this is false, cache is used. If object is not in cache, return empty values.'
        ),
        'outputmode' => Array(
            'Om', 'output_mode', 'cli', 'cli', '',
            'Use this output mode {cli|csv|dump}'
        ),
        'outputverb' => Array(
            'Ov', 'output_verbosity', 'ids', 'ids', '',
            'Use this output verbosity {id,expanded}'
        ),
        'zdsn' => Array(
            'Zd', 'zabbix_dsn', 'pgsql:host=127.0.0.1;port=5432;dbname=zabbix', 'pgsql:host=127.0.0.1;port=5432;dbname=zabbix', '',
            'Use this zabbix Database settings'
        ),
        'zdbuser' => Array(
            'Zu', 'zabbix_db_user', 'zabbix', 'zabbix', '',
            'Use this zabbix Database user'
        ),
        'zdbpw' => Array(
            'Zp', 'zabbix_db_pw', '', '', '',
            'Use this zabbix Database password'
        ),
        'zid' => Array(
            'Zi', 'zabbix_id', '1', '1', '',
            'Use this zabbix server ID'
        ),
        'mdsn' => Array(
            'Md', 'monda_dsn', 'pgsql:host=127.0.0.1;port=5432;dbname=monda', 'pgsql:host=127.0.0.1;port=5432;dbname=monda', '',
            'Use this monda Database settings'
        ),
        'mdbuser' => Array(
            'Mu', 'monda_db_user', 'monda', 'monda', '',
            'Use this monda Database user'
        ),
        'mdbpw' => Array(
            'Mp', 'monda_db_pw', 'M0nda', 'M0nda', '',
            'Use this monda Database password'
        ),
        'zaburl' => Array(
            'ZU', 'zabbix_url', 'http://localhost/zabbix', 'http://localhost/zabbix', '',
            'Base of zabbix urls'
        ),
        'zapiurl' => Array(
            'Za', 'zabbix_api_url', 'http://localhost/zabbix/api_jsonrpc.php', 'http://localhost/zabbix/api_jsonrpc.php', '',
            'Use this zabbix API url'
        ),
        'zapiuser' => Array(
            'Zau', 'zabbix_api_user', 'monda', 'monda', '',
            'Use this zabbix API user'
        ),
        'zapipw' => Array(
            'Zap', 'zabbix_api_pw', '', '', '',
            'Use this zabbix API password'
        ),
        'csvdelim' => Array(
            '', 'csv_delimiter', ';', ';', '',
            'Use this delimiter for CSV output'
        ),
        'csvenc' => Array(
            '', 'csv_enclosure', '"', '"', '',
            'Use this enclosure for CSV output'
        ),
        'zabbix_history_table' => Array(
            'Zht', 'zabbix_history_table', 'history', 'history', '',
            'Zabbix history table to work on'
        ),
        'zabbix_history_uint_table' => Array(
            'Zhut', 'zabbix_history_uint_table', 'history_uint', 'history_uint', '',
            'Zabbix history_uint table to work on'
        ),
        'apicacheexpire' => Array(
            'Ace', 'api_cache_expire', '24 hours', '24 hours', '',
            'Maximum time to cache api requests. Use 0 to not cache.'
        ),
        'sqlcacheexpire' => Array(
            'Sce', 'sql_cache_expire', '1 hour', '1 hour', '',
            'Maximum time to cache sql requests. Use 0 to not cache.'
        ),
        'nocache' => Array(
            'nc', 'nocache', '', 'no', '',
            'Disable both SQL and API cache'
        ),
        'sow' => Array(
            'sw', 'sow', 'Monday', 'Monday', '',
            'Star day of week'
        ),
        // Time window parameters
        'start' => Array(
            's', 'start', '1420063200', '2015-01-01 00:00', '',
            'Start time of analysis.'
        ),
        'end' => Array(
            'e', 'end', '1433837100', '-1 hour', '',
            'End time of analysis.'
        ),
        'description' => Array(
            'd', 'window-description', '', '', '',
            'Window description.'
        ),
        'length' => Array(
            'l', 'window_length', '', 'All', '',
            'Window length. Leave empty to get all lengths.'
        ),
        'wsort' => Array(
            'ws', 'windows_sort', 'loi/-', 'loi/-', '',
            'Sort order of windows to select ({random|start|length|loi|loih|updated}/{+|-}'
        ),
        'empty' => Array(
            'm', 'only_empty_results', '', 'no', '',
            'Work only on results which are empty (skip already computed objects)'
        ),
        'loionly' => Array(
            'L', 'only_with_loi', '', 'no', '',
            'Select only objects which have loi>0'
        ),
        'createdonly' => Array(
            'c', 'only_just_created_windows', '', 'no', '',
            'Select only windows which were just created and contains no data'
        ),
        'updated' => Array(
            'u', 'windows_updated_before', '', 'no care', '',
            'Select only windows which were updated less than datetime'
        ),
        'wids' => Array(
            'w', 'window_ids', '', 'no care', '',
            'Select only windows with this ids'
        ),
        'chgloi' => Array(
            'Cl', 'change_loi', '', 'None', '',
            'Change loi of selected windows. Can be number, +number or -number'
        ),
        'rename' => Array(
            'Rn', 'rename', '', 'None', '',
            'Rename selected window(s). Can contain macros %Y, %M, %d, %H, %i, %l, %F'
        ),
        'max_windows' => Array(
            'Wm', 'max_windows', '', 'All', '',
            'Maximum number of windows to fetch (LIMIT SELECT)'
        )
    );

    private static function parseOne($key,$short,$long,$desc,$default=null,$defaulthelp=false,$choices=false) {
        
        self::$getopts[$key]=Array(
            "short" => $short,
            "long" => $long,
            "description" => $desc,
            "default" => $default,
            "defaulthelp" => $defaulthelp,
            "choices" => $choices
        );
        if ($short && array_key_exists($short,self::$params)) {
            $value=stripslashes(self::$params[$short]);
        } elseif (array_key_exists($long,self::$params)) {
            $value=stripslashes(self::$params[$long]);
        } elseif (array_key_exists("_$short",self::$params)) {
            $value=!self::$params["_$short"];
        } elseif (array_key_exists("_$long",self::$params)) {
            $value=!self::$params["_$long"];
        } else {
            $value=$default;
            self::$defaults[]=$key;
        }
        
        self::$opts->$key=$value;
        if ($key=="debug" || $key=="logfile") {
            Debugger::setLogger(New \App\Model\CliLogger());            
        }

        if ($choices) {
            if (array_search($value,$choices)===false) {
                self::mexit(14,sprintf("Bad option %s for parameter %s(%s). Possible values: {%s}\n",$value,$short,$long,join($choices,"|")));
            }
        }
        if (isset(self::$opts->$key)) {
            Debugger::log("Setting option $long($desc) to ".  strtr(Debugger::dump(self::$opts->$key,true),"\n"," ")."\n",Debugger::INFO);
        }
    }
    
    static function isDefault($key) {
        if (array_search($key,self::$opts->defaults)===false) {
            return(false);
        } else {
            return(true);
        }
    }
    
    public function help($name=false) {
        Debugger::log(sprintf("[Common options for %s]:\n",$name),Debugger::WARNING);
        if (!self::get("xhelp")) {
            return;
        }
        foreach (self::$getopts as $key=>$opt) {
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"]=$opt["default"];
            }
            if (array_key_exists("choices",$opt) && is_array($opt["choices"])) {
                $choicesstr="Choices: {".join("|",$opt["choices"])."}\n";
            } else {
                $choicesstr="";
            }
            if (self::isDefault($key)) {
                $avalue="Default";
            } else {
                if (is_array(self::$opts->$key)) {
                    $avalue=join(",",self::$opts->$key);
                } elseif (is_bool(self::$opts->$key)) {
                    $avalue=sprintf("%b",self::$opts->$key);    
                } else {
                    $avalue=self::$opts->$key;
                }
            }
            Debugger::log(sprintf("-%s|--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n",
                    $opt["short"],$opt["long"],     $opt["description"],    $opt["defaulthelp"], $avalue, $choicesstr),Debugger::WARNING);
        }
    }
    
    static function get($name) {
        if ($name) {
            if (isset(self::$opts->$name)) {
                return(self::$opts->$name);
            } else {
                return(false);
            }
        } else {
            return(self::$opts);
        }
    }

    public static function read($params) {
   
        self::$params=$params;
        foreach (self::$confparams as $parm=>$arr) {
            self::parseOne($parm,$arr[0],$arr[1],$arr[5],$arr[2],$arr[3],$arr[4]);
        }
        foreach ($params as $param=>$value) {
            if ($param=="action") continue;
            if ($param=="foo") continue;
            $found=false;
            foreach (self::$confparams as $cparam) {
                if ($param==$cparam[0] || $param==$cparam[1]) {
                    $found=true;
                    continue;
                }
            }
            if (!$found) {
                Debugger::log("Unknown parameter $param!\n",Debugger::WARNING);
            }
        }
        if (isset(self::$opts->nocache)) {
            self::$opts->sql_cache_expire=0;
            self::$opts->api_cache_expire=0;
        }
        self::$opts->start= TimeUtils::timetoseconds(self::$opts->start);
        if (self::$opts->start<631148400) {
            self::mexit(4,sprintf("Bad start time (%d)?!\n",date("Y-m-d",self::$opts->start)));
        }
    }

}

?>
