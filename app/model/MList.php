<?php

namespace App\Model;

use ZabbixApi\ZabbixApi,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    Nette\Utils\ArrayList,
    \Exception;

/**
 * Monda List of objects
 */
class MList extends ArrayList {
    
    public function add($obj) {
        self::getIterator()->append($obj);
    }

    public function getAll($obj) {
        self::getIterator()->getArrayCopy();
    }
}

