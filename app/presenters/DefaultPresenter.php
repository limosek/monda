<?php

namespace App\Presenters;

use \Exception,
    Nette,
    App\Model,
    App\Model\Options,
    Tracy\Debugger,
    \App\Model\CliLogger,
    Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
class DefaultPresenter extends BasePresenter {

    public $moduleactions = Array(
        "zabbix" => "Zabbix server operations",
        "tw" => "Time window operations"
    );
    public $modulehelp = "Monitoring system data analysis";

}
