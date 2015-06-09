<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class Host extends Nette\Object {
    
   private $itemGroup;
   
   static function Info($itemids) {
       
   }
   
   static function Stats($itemids) {
       
   }
   
}

?>
