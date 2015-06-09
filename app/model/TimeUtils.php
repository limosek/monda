<?php
namespace App\Model;

use Nette,
    Nette\Utils\DateTime as DateTime;

/**
 * ItemStat global class
 */
class TimeUtils extends Nette\Object {
    
    const _1HOUR=3600;
    const _1DAY=86400;
    const _1WEEK=604800;
    const _1MONTH=2505600;
    const _1MONTH28=2419200;
    const _1MONTH30=2592000;
    const _1MONTH31=2678400;
    const _1YEAR=31536000;
    const TW_STEP=300;
    
   static function timetoseconds($t) {
        if ($t[0] == "@") {
            return(substr($t, 1));
        } elseif (is_numeric($t)) {
            return($t);
        } elseif (preg_match("/(\d\d\d\d)\_(\d\d)\_(\d\d)\_(\d\d)(\d\d)/", $t, $r)) {
            $y = $r[1];
            $m = $r[2];
            $d = $r[3];
            $h = $r[4];
            $M = $r[5];
            $dte = New DateTime("$y-$m-$d $h:$M".date("P"));
            return(date_format($dte, "U"));
        } elseif (preg_match("/(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)/", $t, $r)) {
            $y = $r[1];
            $m = $r[2];
            $d = $r[3];
            $h = $r[4];
            $M = $r[5];
            $dte = New DateTime("$y-$m-$d $h:$M".date("P"));
            return(date_format($dte, "U"));
        } else {
            $dte = New DateTime($t);
            return(date_format($dte, "U"));
        }
    }
    
    static function roundTime($tme) {
        return(round($tme/self::TW_STEP)*self::TW_STEP);
    }
   
}

?>
