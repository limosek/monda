<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    Tracy\Debugger;

/**
 * TimeWindow global class
 */
class TimeWindow extends Nette\Object {
    private $zabbixId;
    private $start;
    private $end;
    private $seconds;
    private $stats;
    
}

?>
