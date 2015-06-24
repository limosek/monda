<?php

namespace App\Presenters;

use \Exception,
    Nette,
    \Tracy\Debugger,
    App\Model,
    App\Model\CliLogger,
    App\Model\Tw,
    App\Model\Options,
    Nette\Utils\DateTime as DateTime;

class zabbixPresenter extends BasePresenter {

    public $module = "zabbix";
    public $moduleparams = Array(
        //key,          default,    defaulthelp,    choices
        //description
        'dsn' => Array(
            'pgsql:host=127.0.0.1;port=5432;dbname=zabbix', 'pgsql:host=127.0.0.1;port=5432;dbname=zabbix', '',
            'Use this zabbix Database settings'
        ),
        'dbuser' => Array(
            'zabbix', 'zabbix', '',
            'Use this zabbix Database user'
        ),
        'dbpw' => Array(
            '', '', '',
            'Use this zabbix Database password'
        ),
        'id' => Array(
            '1', '1', '',
            'Use this zabbix server ID'
        ),
        'url' => Array(
            'http://localhost/zabbix', 'http://localhost/zabbix', '',
            'Base of zabbix urls'
        ),
        'apiurl' => Array(
            'http://localhost/zabbix/api_jsonrpc.php', 'http://localhost/zabbix/api_jsonrpc.php', '',
            'Use this zabbix API url'
        ),
        'apiuser' => Array(
            'monda', 'monda', '',
            'Use this zabbix API user'
        ),
        'apipw' => Array(
            '', '', '',
            'Use this zabbix API password'
        ),
        'history_table' => Array(
            'history', 'history', '',
            'Zabbix history table to work on'
        ),
        'history_uint_table' => Array(
            'history_uint', 'history_uint', '',
            'Zabbix history_uint table to work on'
        ),
        'apiexpire' => Array(
            '24 hours', '24 hours', '',
            'Maximum time to cache api requests. Use 0 to not cache.'
        )
    );
    public $moduleactions = Array(
        "add" => "Add zabbix server to Monda",
        "del" => "Deletr zabbix server from Monda"
    );
    public $modulehelp = "Zabbix server operations";
    public $modulehints = Array(
        "Zabbix server manipulation is safe for zabbix server. It will manipulate only with Monda DB."
    );

}
