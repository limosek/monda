<?php

namespace App\Model;

use Nette,
    \App\Presenters\BasePresenter,
    Tracy\Debugger;

/**
 * ItemStat global class
 */
class Options extends Nette\Object {

    private static $opts;   // Options values parsed from inputs
    const TCHARS="\t\n\r\0\x0B'\"";

    private static $copts = Array();            // Options informations
    private static $defaults = Array();         // Options which are defaults
    private static $paramsrc = Array();         // Param source (file, cli, env)
    private static $confparams = Array(         // Parameters for module
        //key,               short,  default,    defaulthelp,    choices
        //description
        'debug' => Array(
            'D', 'warning', '', Array("debug", "info", "warning", "error", "critical"),
            'Debug level (debug,info,warning,error,critical)'
        ),
        'conffile' => Array(
            false, ((PHP_SAPI == "cli") ? '~/.mondarc' : __DIR__ . '/../config/monda.rc'), '', '',
            'Config file location'
        ),
        'prio' => Array(
            'N', 'low', '', Array('low', 'std', 'high'),
            'Set monda process priority'
        ),
        'logfile' => Array(
            false, 'php://stderr', '', '',
            'Log file'
        ),
        'help' => Array(
            'h', '', '', '',
            'Help. If used with module, help will be module specific'
        ),
        'xhelp' => Array(
            'xh', '', '', '',
            'Advanced help for all options'
        ),
        'progress' => Array(
            'P', '', '', '',
            'Progress informations on stderr'
        ),
        'configinfo' => Array(
            'C', '', '', '',
            'Configuration information'
        ),
        'dry' => Array(
            'R', '', 'no', '',
            'Only show what would be done. Do not touch db.'
        ),
        'outputmode' => Array(
            'Om', 'cli', 'cli', '',
            'Use this output mode {cli|csv|dump}'
        ),
        'outputverb' => Array(
            'Ov', 'ids', 'ids', '',
            'Use this output verbosity {id,expanded}'
        ),
        'dsn' => Array(
            false, 'pgsql:host=127.0.0.1;port=5432;dbname=monda', 'pgsql:host=127.0.0.1;port=5432;dbname=monda', '',
            'Use this monda Database settings'
        ),
        'dbuser' => Array(
            false, 'monda', 'monda', '',
            'Use this monda Database user'
        ),
        'dbpw' => Array(
            false, 'M0nda', 'M0nda', '',
            'Use this monda Database password'
        ),
        'sow' => Array(
            'sw', 'monday', 'monday', Array('monday', 'sunday'),
            'Star day of week'
        ),
        // Time window parameters
        'start' => Array(
            's', 'january 1', 'january 1', '',
            'Start time of analysis.'
        ),
        'end' => Array(
            'e', '-1 hour', '-1 hour', '',
            'End time of analysis.'
        ),
        'description' => Array(
            'd', '', '', '',
            'Window description.'
        ),
        'length' => Array(
            'l', '', 'All', '',
            'Window length. Leave empty to get all lengths.'
        ),
        'wsort' => Array(
            'ws', 'loi/-', 'loi/-', '',
            'Sort order of windows to select ({random|start|length|loi|loih|updated}/{+|-}'
        ),
        'empty' => Array(
            'm', '', 'no', '',
            'Work only on results which are empty (skip already computed objects)'
        ),
        'loionly' => Array(
            'L', '', 'no', '',
            'Select only objects which have loi>0'
        ),
        'createdonly' => Array(
            'c', '', 'no', '',
            'Select only windows which were just created and contains no data'
        ),
        'updated' => Array(
            'u', '', 'no care', '',
            'Select only windows which were updated less than datetime'
        ),
        'wids' => Array(
            'w', '', 'no care', '',
            'Select only windows with this ids'
        ),
        'chgloi' => Array(
            'Cl', '', 'None', '',
            'Change loi of selected windows. Can be number, +number or -number'
        ),
        'rename' => Array(
            'Rn', '', 'None', '',
            'Rename selected window(s). Can contain macros %Y, %M, %d, %H, %i, %l, %F'
        ),
        'max_windows' => Array(
            'Wm', '', 'All', '',
            'Maximum number of windows to fetch (LIMIT SELECT)'
        )
    );

    static function isDefault($key) {
        if (array_key_exists($key, self::$defaults)) {
            return(self::$defaults[$key]);
        } else {
            return(false);
        }
    }

    public function help($name = false) {
        foreach (self::$copts as $key => $opt) {
            if ($name) {
                if (!preg_match("/^$name\./", $key))
                    continue;
            }
            if (!$opt["defaulthelp"]) {
                $opt["defaulthelp"] = $opt["default"];
            }
            if (array_key_exists("choices", $opt) && is_array($opt["choices"])) {
                $choicesstr = "Choices: {" . join("|", $opt["choices"]) . "}\n";
            } else {
                $choicesstr = "";
            }
            if (self::isDefault($key)) {
                $avalue = "Default";
            } else {
                if (is_array(self::$opts->$key)) {
                    $avalue = join(",", self::$opts->$key);
                } elseif (is_bool(self::$opts->$key)) {
                    $avalue = sprintf("%b", self::$opts->$key);
                } else {
                    $avalue = self::$opts->$key;
                }
            }
            if ($opt["short"]) {
                Debugger::log(sprintf("-%s|--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n", $opt["short"], $opt["long"], $opt["description"], $opt["defaulthelp"], $avalue, $choicesstr), Debugger::WARNING);
            } else {
                Debugger::log(sprintf("--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n", $opt["long"], $opt["description"], $opt["defaulthelp"], $avalue, $choicesstr), Debugger::WARNING);
            }
        }
    }

    static function get($name, $domain = false) {
        if ($name && $domain) {
            $name = "$domain.$name";
        }
        if ($name) {
            if (isset(self::$opts->$name)) {
                return(self::$opts->$name);
            } else {
                return(false);
            }
        } else {
            if ($domain) {
                $opts = Array();
                foreach (self::$opts as $key => $opt) {
                    if (preg_match("/^$domain\./", $key)) {
                        $opts[$key] = $opt;
                    }
                }
                return($opts);
            } else {
                return(self::$opts);
            }
        }
    }

    public static function info($param=false) {
        if (!$param) {
            $ret=Array();
            foreach (self::$copts as $key=>$opt) {
                $ret["$key"]=self::info($key);
            }
            return($ret);
        }
        return(Array(
            "key" => $param,
            "value" => self::get($param),
            "default" => self::isDefault($param),
            "setfrom" => self::$paramsrc[$param],
            "copt" => self::$copts[$param]
        ));
    }

    public static function readFile($filename) {
        $params=Array();
        $f = fopen($filename, "r");
        while ($line = fgets($f)) {
            if (preg_match("#^-#", $line)) {
                if (preg_match("#^(-[a-zA-Z0-9_\-]*) {1,4}(.*)$#", $line, $regs)) {
                    $option = trim($regs[1], self::TCHARS);
                    $value = trim($regs[2], self::TCHARS);
                    if (substr($option,0,2)=="--") {
                        $option=substr($option,2);
                    }
                    if (substr($option,0,1)=="-") {
                        $option=substr($option,1);
                    }
                    $params[$option]=$value;
                } else {
                    $params[$option]=true;
                }
            }
        }
        fclose($f);
        self::read($params,$filename);
    }
    
    public static function readEnv() {
        $params=Array();
        foreach (self::$confparams as $key=>$cparam) {
            $envkey="MONDA_".strtr($key,".","_");
            if (getenv($envkey)) {
                $params[$key]=addslashes(getenv($envkey));
            }
        }
        self::read($params,"env");
    }
    
    private static function parseOne($params, $key, $short, $long, $desc, $default = null, $defaulthelp = false, $choices = false, $from = false) {

        self::$copts[$key] = Array(
            "short" => $short,
            "long" => $long,
            "description" => $desc,
            "default" => $default,
            "defaulthelp" => $defaulthelp,
            "choices" => $choices
        );
        $fromdefault=false;
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
            $fromdefault=true;
        }
        
        if ($choices) {
            if (array_search($value, $choices) === false) {
                BasePresenter::mexit(14, sprintf("Bad option %s for parameter %s(%s). Possible values: {%s}\n", $value, $short, $long, join($choices, "|")));
            }
        }
        $objkey=$key;
        if (isset(self::$opts->$objkey)) {
            if (!$fromdefault) {
                self::$paramsrc[$key] = $from;
                self::$opts->$objkey = $value;
                if (!self::isDefault($key) && $value!=self::$opts->$objkey) {
                    CliLogger::log("Overwriting option $key from $from.\n",Debugger::WARNING);
                }
                self::$defaults[$key]=false;
            }
        } else {
            self::$opts->$objkey = $value;
            self::$paramsrc[$key] = $from;
            self::$defaults[$key]=$fromdefault;
            if ($long="debug") CliLogger::__init();
            if ($fromdefault) {
                CliLogger::log("Default option $key($desc) to " . strtr(Debugger::dump(self::$opts->$objkey, true), "\n", " ") . "\n", Debugger::INFO);
            } else {
                CliLogger::log("Setting option $key($desc) (from $from) to " . strtr(Debugger::dump(self::$opts->$objkey, true), "\n", " ") . "\n", Debugger::WARNING);
            }
        }
    }

    static function extend($domain, $opts) {
        foreach ($opts as $key => $opt) {
            $idx = "$domain.$key";
            self::$confparams[$idx] = Array(
                false, $opt[0], $opt[1], $opt[2],$opt[3]
            );
        }
    }

    public static function read($params=Array(),$from=PHP_SAPI) {
        foreach (self::$confparams as $parm => $arr) {
            self::parseOne($params, $parm, $arr[0], $parm, $arr[4], $arr[1], $arr[2], $arr[3], $from);
        }
        foreach ($params as $param => $value) {
            if ($param == "action")
                continue;
            if ($param == "error")
                continue;
            $found = false;
            foreach (self::$confparams as $key => $cparam) {
                if ($param == $key || $param == $cparam[0]) {
                    $found = true;
                    continue;
                }
            }
            if (!$found) {
                Debugger::log("Unknown parameter $param!\n", Debugger::WARNING);
            }
        }
        self::$opts->start = TimeUtils::timetoseconds(self::$opts->start);
        if (self::$opts->start < 631148400) {
            BasePresenter::mexit(4, sprintf("Bad start time (%d)?!\n", date("Y-m-d", self::$opts->start)));
        }
    }

}

?>
