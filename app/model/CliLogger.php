<?php

namespace App\Model;

use Nette,
    \Tracy\Dumper,
    \Tracy\Debugger;

/**
 * ItemStat global class
 */
class CliLogger extends \Tracy\Logger implements \Tracy\ILogger {

    static $loglevel;
    private static $levels = Array(
        "debug" => 0,
        "info" => 1,
        "warning" => 2,
        "error" => 3,
        "critical" => 4
    );
    static $logfd;
    static $wwwlogger;

    public function __construct($directory, $email = NULL, BlueScreen $blueScreen = NULL) {
        parent::__construct($directory, $email, $blueScreen);
        self::__init();
    }
    
    public function __init($directory = NULL, $email = NULL, BlueScreen $blueScreen = NULL) {
        if (!$directory) {
            $directory = __DIR__."/../log/";
        }
        if (getenv("MONDA_DEBUG")) {
            if (array_key_exists(getenv("MONDA_DEBUG"), self::$levels)) {
                self::$loglevel = self::$levels[getenv("MONDA_DEBUG")];
                Debugger::$productionMode = false;
            } else {
                self::log("Unknown log level!\n", Debugger::ERROR);
            }
        } else {
            if (Options::get("debug")) {
                if (array_key_exists(Options::get("debug"), self::$levels)) {
                    self::$loglevel = self::$levels[Options::get("debug")];
                    Debugger::$productionMode = false;
                } else {
                    self::log("Unknown log level!\n", Debugger::ERROR);
                }
            }
        }
        self::$wwwlogger=New \Tracy\Logger($directory, $email, $blueScreen);
        if (PHP_SAPI=="cli") {
            if (Options::get("logfile")) {
                self::$logfd=fopen(Options::get("logfile"),"w");
            } else {
                self::$logfd=STDERR;
            }
        }
    }

    public function log($message, $priority = self::INFO) {
        if (!isset(self::$loglevel)) {
            self::__init();
        }
        if (PHP_SAPI=="cli") {
            if (array_key_exists($priority,self::$levels)) {
                if (self::$levels[$priority] >= self::$loglevel) {
                    fprintf(self::$logfd, $message);
                } 
            }
        }
    }

}

?>
