<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,App\Model\Opts,App\Model\Monda,
        Tracy\Debugger,
        App\Model\CliDebug,
        Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    
    public $exportdata;

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
        if (!getenv("MONDA_CLI")) {
            throw New Exception("Error #$code: $msg");
        } else {
            exit($code);
        }
    }
    
    function startup() {
        CliDebug::startup();
        Monda::$apicache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_APICACHEDIR")));
        Monda::$sqlcache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_SQLCACHEDIR")));
        Monda::$cache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage(getenv("MONDA_CACHEDIR")));
        Opts::startup();
        Opts::addOpt(
                "R", "dry", "Only show what would be done. Do not touch db.", false, "no"
        );
        Opts::addOpt(
                "za", "zabbix_api", "Use Zabbix API to retrieve objects. If this is false, cache is used. If object is not in cache, return empty values.", false, "API disabled"
        );
        Opts::addOpt(
                "Om", "output_mode", "Use this output mode {cli|csv|dump}", "cli", "cli"
        );
        Opts::addOpt(
                false, "csv_separator", "Use this CSV separator", ";", ";"
        );
        Opts::addOpt(
                false, "csv_field_enclosure", "Use this CSV enclosure", '"', '"'
        );
        Opts::addOpt(
                false, "csv_header", "Use CSV header", true, true
        );
        Opts::addOpt(
                false, "csv_fields", "Output only this fields", false, false
        );
        Opts::addOpt(
                "Ov", "output_verbosity", "Use this output verbosity {id,expanded}", "ids", "ids"
        );
        Opts::addOpt(
                "Zd", "zabbix_dsn", "Use this zabbix Database settings", "pgsql:host=127.0.0.1;port=5432;dbname=zabbix", "pgsql:host=127.0.0.1;port=5432;dbname=zabbix"
        );
        Opts::addOpt(
                "Zu", "zabbix_db_user", "Use this zabbix Database user", "zabbix", "zabbix"
        );
        Opts::addOpt(
                "Zp", "zabbix_db_pw", "Use this zabbix Database password", "", ""
        );
        Opts::addOpt(
                "Zi", "zabbix_id", "Use this zabbix server ID", "1", "1"
        );
        Opts::addOpt(
                false, "zabbix_alias", "Use this zabbix server alias", getenv("MONDA_ZABBIX_ALIAS"), "\${MONDA_ZABBIX_ALIAS}"
        );
        Opts::addOpt(
                "Md", "monda_dsn", "Use this monda Database settings", "pgsql:host=127.0.0.1;port=5432;dbname=monda", "pgsql:host=127.0.0.1;port=5432;dbname=monda"
        );
        Opts::addOpt(
                "Mu", "monda_db_user", "Use this monda Database user", "monda", "monda"
        );
        Opts::addOpt(
                "Mp", "monda_db_pw", "Use this monda Database password", "M0nda", "M0nda"
        );
        Opts::addOpt(
                "ZU", "zabbix_url", "Base of zabbix urls", "http://localhost/zabbix", "http://localhost/zabbix"
        );
        Opts::addOpt(
                "Za", "zabbix_api_url", "Use this zabbix API url", "<zabbix_url>/api_jsonrpc.php", "{zabbix_url}/api_jsonrpc.php"
        );
        Opts::addOpt(
                "Zau", "zabbix_api_user", "Use this zabbix API user", "monda", "monda"
        );
        Opts::addOpt(
                "Zap", "zabbix_api_pw", "Use this zabbix API password", "", ""
        );
        Opts::addOpt(
                "Zht", "zabbix_history_table", "Zabbix history table to work on", "history", "history"
        );
        Opts::addOpt(
                "Zhut", "zabbix_history_uint_table", "Zabbix history_uint table to work on", "history_uint", "history_uint"
        );
        Opts::addOpt(
                "Ace", "api_cache_expire", "Maximum time to cache api requests. Use 0 to not cache.", "24 hours", "24 hours"
        );
        Opts::addOpt(
                "Im", "max_rows", "Maximum number of rows to get (LIMIT for SELECT)", 300, 300
        );
        Opts::addOpt(
                false, "min_loi", "Select only objects which have loi bbigger than this", 0, 0
        );
        Opts::addOpt(
                "Sce", "sql_cache_expire", "Maximum time to cache sql requests. Use 0 to not cache.", "1 hour", "1 hour"
        );
        Opts::addOpt(
                "nc", "nocache", "Disable both SQL and API cache", false, "no"
        );
        Opts::addOpt(
                "sw", "sow", "Star day of week", "Monday", "Monday"
        );
        parent::startup();
    }
    
    public function postCfg() {
        Opts::setOpt("csv_separator", htmlspecialchars_decode(Opts::getOpt("csv_separator")),"default");
        Opts::setOpt("csv_field_enclosure", htmlspecialchars_decode(Opts::getOpt("csv_field_enclosure")),"default");
        if (!Opts::isDefault("csv_fields")) {
            Opts::setOpt("csv_fields", array_flip(preg_split("/,/", Opts::getOpt("csv_fields"))),"default");
        }
        if (Opts::isOpt("nocache")) {
            Opts::setOpt("sql_cache_expire", 0, "default");
            Opts::setOpt("api_cache_expire", 0, "default");
        }
        if (Opts::isOpt("help") || Opts::isOpt("xhelp") || Opts::isOpt("config_test")) {
            $this->Help();
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
            if ($i == 0 && Opts::getOpt("csvheader")) {
                foreach ($row as $r => $v) {
                    echo sprintf('%s%s%s%s',Opts::getOpt("csvfield"),$r,Opts::getOpt("csvfield"),Opts::getOpt("csvsep"));
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
                if (is_array(Opts::getOpt("csvfields"))) {
                    if (array_key_exists($col,Opts::getOpt("csvfields")) ||array_key_exists($r,Opts::getOpt("csvfields"))) {
                        $print=true;
                    } else {
                        $print=false;
                    }
                }
                if ($print) echo sprintf('%s%s%s%s',Opts::getOpt("csvfield"),$v,Opts::getOpt("csvfield"),Opts::getOpt("csvsep"));
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
        switch (Opts::getOpt("outputmode")) {
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
