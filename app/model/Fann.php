<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;


function limotrain($num_data, $num_input, $num_output) {
    return array(
        "input" => array_fill(0, $num_input, 1),
        "output" => array_fill(0, $num_output, 1),
    );
}

/**
 * ItemStat global class
 */
class Fann extends \Nette\Object {

   function create($inputs,$outputs) {
       $f=fann_create_standard(3,$inputs,3,$outputs);
       return($f);
   }
   
   function train() {
       $f=fann_create_standard(3,1,3,1);
       $t=fann_create_train_from_callback(100,1,1,'limotrain');
       $r=fann_train_on_data($f,$t,20,20,0.1);
       if ($r) {
            echo "aaa";
            fann_save($f."/tmp/b");
       } else {
           echo "bbb";
           echo fann_get_errstr($f)."\n";
       }
   }

}

?>
