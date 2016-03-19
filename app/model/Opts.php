<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    \Exception,
    Tracy\Debugger;

/**
 * TimeWindow global class
 */
class Opts extends Nette\Object {

    private static $data=Array();
    private static $opts=Array();
    private static $setfrom=Array();
    private static $cfgread=Array();
    private static $history=Array();

    public static function startup() {
        
        Opts::addOpt(
                "h", "help", "Help. If used with module, help will be module specific", false, false
        );
        Opts::addOpt(
                "xh", "xhelp", "Advanced help. If used with module, help will be module specific", false, false
        );
        Opts::addOpt(
                "C", "config_test", "Show config options read from INI, env and args", false, false
        );
        Opts::addOpt(
                "D", "debug", "Debug level (debug,info,warning,error,critical)", "warning", "warning"
        );
        Opts::preReadOpts();
    }
    
    /**
     * Read config from INI file
     * @param Array() $contexts contexts to read
     * @param boolean $final If this is final (last call, all options known)
     */
    public static function readCfg($contexts,$final=false) {
        if (file_exists(getenv("MONDARC"))) {
            $fopts = parse_ini_file(getenv("MONDARC"), true);
            $foptions = Array();
            foreach ($contexts as $context) {
                if (array_key_exists($context, Opts::$cfgread)) {
                    continue;
                }

                CliDebug::dbg("Want to read INI context $context.\n");
                Opts::$cfgread[$context]=true;
                if (array_key_exists($context, $fopts)) {
                    $foptions = array_merge($foptions, $fopts[$context]);
                    CliDebug::info("Got config from INI context $context.\n");
                }
            }
            foreach ($foptions as $opt => $value) {
                $found=false;
                foreach (Opts::$opts as $okey=>$ovalue) {
                    if (!strcmp($opt,$ovalue["long"])) {
                        Opts::setOpt($okey,$value,"INI");
                        Opts::$opts[$opt]["defaults"] = false;
                        $found=true;
                    }
                }
                if (!$found && $final) {
                    CliDebug::err("Option '$opt' unknown, ignoring!\n");
                }
            }
        }
    }
    
    public static function preReadOpts() {
        global $argv;

        $configurator = new Nette\Configurator;
        if (getenv("MONDA_CLI")) {
            foreach ($argv as $i => $a) {
                if (!strcmp($a, "--help") ||
                        !strcmp($a, "-h")
                ) {
                    Opts::setOpt("help", true);
                }
                if (!strcmp($a, "-xh") ||
                        !strcmp($a, "--xhelp")
                ) {
                    Opts::setOpt("xhelp", true);
                }
                if (!strcmp($a, "-D") ||
                        !strcmp($a, "--debug")
                ) {
                    Opts::setOpt("debug", $argv[$i + 1]);
                }
                if (!strcmp($a, "-C") ||
                        !strcmp($a, "--config_test")
                ) {
                    Opts::setOpt("config_test", true);
                }
            }
        }
        if (Opts::getOpt("debug") == "debug" || !getenv("MONDA_CLI")) {
            $configurator->setDebugMode(true);
        } else {
            $configurator->setDebugMode(false);
        }
        CliDebug::startup(Opts::getOpt("debug"));
    }

    /**
     * Read config from cmdline arguments
     * @param Array() $params cmdline parameters parsed by clirouter
     * @param boolean $final If this is final (last call, all options known)
     */
    public static function readOpts($params=Array(),$final=false) {
        foreach ($params as $p=>$value) {
            if (!strcmp($p,"action")) continue;
            $found=false;
            foreach (Opts::$opts as $okey=>$ovalue) {
                if (!strcmp($p,$ovalue["short"]) || !strcmp($p,$ovalue["long"])) {
                    Opts::setOpt($okey,$value);
                    Opts::$opts[$okey]["defaults"] = false;
                    $found=true;
                }
            }
            if (!$found && $final) {
                CliDebug::err("Option '$p' unknown!\n");
            }
        }
        if (Opts::getOpt("debug") == "debug") {
            Debugger::enable(Debugger::DEVELOPMENT);
        } else {
            Debugger::enable(Debugger::PRODUCTION);
        }
        if (Opts::getOpt("debug") == "debug") {
            Debugger::enable(Debugger::DEVELOPMENT);
        } else {
            Debugger::enable(Debugger::PRODUCTION);
        }
        CliDebug::startup(Opts::getOpt("debug"));
    }

    public static function setOpt($opt, $value, $from = "cli") {
        if (!array_key_exists($opt,Opts::$opts)) {
            throw New \Exception("Unknown parameter $opt!");
        }
        $long=Opts::$opts[$opt]["long"];
        Opts::$data[$opt] = $value;
        Opts::$setfrom[$opt] = $from;
    }
    
    public static function pushOpt($opt, $value) {
        if (!array_key_exists($opt,Opts::$opts)) {
            throw New \Exception("Unknown parameter $opt!");
        }
        $long=Opts::$opts[$opt]["long"];
        if (array_key_exists($opt,Opts::$history)) {
            if (!is_array(Opts::$history[$opt])) {
                Opts::$history[$opt]=Array();
            }
        } else {
            Opts::$history[$opt]=Array();
        }
        array_push(Opts::$history[$opt],Opts::$data[$opt]);
        Opts::$data[$opt] = $value;
        if (array_key_exists($opt,Opts::$opts)) {
            Opts::$opts[$opt]["defaults"] = false;
        }
    }
    
    public static function popOpt($opt) {
        if (!array_key_exists($opt,Opts::$opts)) {
            throw New \Exception("Unknown parameter $opt!");
        }
        $long=Opts::$opts[$opt]["long"];
        $value=array_pop(Opts::$history[$opt]);
        Opts::$data[$opt] = $value;
        return($value);
    }

    public static function getOpt($opt) {
        if (Opts::isOpt($opt)) {
            return(Opts::$data[$opt]);
        } else {
            return(false);
        }
    }
    
    public static function optToArray($param,$sep=",") {
        if (!Opts::isOpt($param)) {
            $arr=Array();
        } elseif (is_array(Opts::getOpt($param))) {
            $arr=Opts::getOpt($param);
        } elseif (preg_match("/$sep/",Opts::getOpt($param))) {
            $arr = preg_split("/$sep/", Opts::getOpt($param));
        } else {
            $arr=Array(Opts::getOpt($param));
        }
        Opts::setOpt($param,$arr);
        return($arr);
    }

    public static function isOpt($opt) {
        if (!array_key_exists($opt, Opts::$data)) {
            if (!array_key_exists($opt, Opts::$opts)) {
                throw New \Exception("Option '$opt' unknown!");
            }
            return(false);
        } else {
            if (is_bool(Opts::$data[$opt])) {
                return(Opts::$data[$opt]);
            } else {
                return(true);
            }
        }
    }

    public static function isDefault($opt) {
        return(Opts::$opts[$opt]["defaults"]);
    }

    public static function setDefaults() {
        foreach (Opts::$opts as $key => $optdata) {
            if (preg_match("/(<.*>)/", $optdata["default"], $vars)) {
                $key2=substr(substr($vars[1],1),0,-1);
                $optdata["default"]=preg_replace("/($key2)/",Opts::getOpt($key2),$optdata["default"]);
            }
            if (Opts::isDefault($key)) {
                Opts::$data[$key] = $optdata["default"];
                Opts::$opts[$key]["defaults"] = true;
            }
        }
    }

    public static function addOpt($short = false, $long, $description, $default, $info_default, $choices = false) {
        if (!array_key_exists($long, Opts::$opts)) {
            Opts::$opts[$long] = Array(
                "short" => $short,
                "long" => $long,
                "description" => $description,
                "default" => $default,
                "info_default" => $info_default,
                "choices" => $choices,
                "defaults" => true
            );
            return(true);
        } else {
           //CliDebug::dbg("Same parameter '$long' added!\n");
        }
    }

    public static function helpOpts($force=false) {
        
        CliDebug::warn("[Common options:\n");

        if (!Opts::isOpt("xhelp") && !Opts::isOpt("help") && !$force) {
            if (!Opts::isOpt("help")) {
                CliDebug::warn("Use -h to get more info about parameters.\n");
            }
            return;
        }
        foreach (Opts::$opts as $key => $opt) {
            if (is_array($opt["choices"])) {
                $choicesstr = "Choices: {" . join("|", $opt["choices"]) . "}\n";
            } else {
                $choicesstr = "";
            }
            $avalue = Opts::getOpt($key);
            if ($opt["short"]) {
                $short = "-" . $opt["short"] . "|";
            } else {
                $short = "";
            }
            if (Opts::isOpt("xhelp")) {
                CliDebug::warn(sprintf("%s--%s 'value':\n   %s\n   Default: <%s>\n   Actual value: %s\n   %s\n", $short, $opt["long"], $opt["description"], $opt["defaulthelp"], $avalue, $choicesstr));
            } else {
                CliDebug::warn(sprintf("%s--%s %s\n", $short, $opt["long"], $opt["description"]));
            }
        }
        if (!Opts::isOpt("xhelp")) {
            CliDebug::warn("Use -xh to get more info about parameters.\n");
        }
    }
    
    public static function showOpts($force=false) {
        if (!Opts::isOpt("config_test") && !$force) {
            return;
        }
        CliDebug::warn("[Options read from cfg, env and cli:\n");
        foreach (Opts::$opts as $key => $opt) {
            if (array_key_exists($key,Opts::$setfrom)) {
                $setfrom=Opts::$setfrom[$key];
            } else {
                $setfrom=false;
            }
            if (!Opts::$opts[$key]["defaults"] || Opts::getOpt("debug")=="debug") {
                if (is_array(Opts::getOpt($key))) {
                    CliDebug::warn(sprintf("%s=[%s]",$opt["long"],join(",",Opts::getOpt($key))));
                } else {
                    CliDebug::warn(sprintf("%s=%s",$opt["long"],Opts::getOpt($key)));
                }
                CliDebug::info(sprintf(" (from=%s, default=%b)", $setfrom, Opts::$opts[$key]["defaults"]));            
                CliDebug::warn("\n");
            }
        }
    }

}

?>
