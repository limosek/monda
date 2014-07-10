<?php

class Monda extends stdClass {
    var $cache; // Cache
    var $api;   // ZabbixApi class
    var $zq;    // Zabbix query link id
    var $mq;    // Monda query link id
    
    
    function errorexit($str, $code) {
        fprintf(STDERR, $str, $code);
        exit($code);
    }
    
    function init_api() {
        if (!defined(ZABBIX_URL) || !defined(ZABBIX_USER) || !defined(ZABBIX_PW)) {
            $this->api = new ZabbixApi(ZABBIX_URL, ZABBIX_USER, ZABBIX_PW);
        } else {
            errorexit("You must define ZABBIX_URL, ZABBIX_USER and ZABBIX_PW macros in config.inc.php!\n", 4);
        }
        return($api);
    }

    function init_sql() {
        if (ZABBIX_DB_TYPE == "MYSQL") {
            //$zq = mysql_connect(ZABBIX_DB_SERVER . ":" . ZABBIX_DB_PORT, ZABBIX_DB_USER, ZABBIX_DB_PASSWORD);
            //mysql_select_db(ZABBIX_DB, $zq);
            $this->errorexit("Mysql not supported yet!");
        }
        if (ZABBIX_DB_TYPE == "POSTGRESQL") {
            $zq = pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password=%s", ZABBIX_DB_SERVER, ZABBIX_DB_PORT, ZABBIX_DB, ZABBIX_DB_USER, ZABBIX_DB_PASSWORD));
        }

        if (MONDA_DB_TYPE == "MYSQL") {
            $this->errorexit("Mysql not supported yet!");
        }
        return($api);
    }
    
    /**
     * Init class function
     *  Params are derived from global defines
     */
    function Monda($options=false,$initapi=false) {
        if ($initapi) {
            $this->api=$this->init_api();
        }
        $this->init_sql();
    }
}


?>
