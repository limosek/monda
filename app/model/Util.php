<?php

namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    Tracy\Debugger;

/**
 * TimeWindow global class
 */
class Util extends Nette\Object {

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
    
    static public function roundTime($tme) {
        return(round($tme/self::TW_STEP)*self::TW_STEP);
    }
    
    static public function dateTime($tme) {
        return(date("Y-m-d H:i",$tme));
    }
    
    static function zabbixGraphUrl1($itemids, $start, $seconds) {
        if ($itemids) {
            $itemidsstr = "";
            foreach ($itemids as $i) {
                $itemidsstr.="itemids[$i]=$i&";
            }
        } else {
            $itemidsstr = "";
        }
        $url=sprintf("%s/history.php?", Opts::getOpt("zabbix_url")) . sprintf("action=batchgraph&%s&graphtype=0&period=%d&stime=%d", $itemidsstr, $seconds, $start);
        if (Opts::getOpt("anonymize_urls")) {
            $url=Util::anonymize($url,Opts::getOpt("anonymize_key"));
        }
        return($url);
    }
    
    static function zabbixGraphUrl2($itemids, $start, $seconds) {
        if ($itemids) {
            $itemidsstr = "";
            $j=0;
            foreach ($itemids as $i) {
                $itemidsstr.="itemids[$j]=$i&";
                $j++;
            }
        } else {
            $itemidsstr = "";
        }
        $url=sprintf("%s/chart.php?", Opts::getOpt("zabbix_url")) . sprintf("period=%d&stime=%s&%s&type=0&batch=1&updateProfile=0&profileIdx=&profileIdx2=&width=1024", $seconds, date("YmdHis",$start), $itemidsstr);
        if (Opts::getOpt("anonymize_urls")) {
            $url=Util::anonymize($url,Opts::getOpt("anonymize_key"));
        }
        return($url);
    }
    
    function numtostep($num,$min,$max,$steps=10) {
        $range=abs($max-$min);
        if ($range==0 || $max==0) {
            return(1);
        }
        $step=$range/$steps;
        $ret=min(1+round($steps*($num/$max)),$steps);
        return($ret);
    }
    
    function addclass($props,$class) {
        $props->class[]=$class;
        $props->$class=1;
        return($props);
    }
    
    function isclass($props,$class) {
        return(isset($props->$class));
    }
    
    function encrypt($text, $salt) {
        return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
    }

    function simple_decrypt($text, $salt) {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }
    
    function anonymize($text,$key) {
        return(sha1($text.$key));
    }
    
    function arr_closestkey($array, $k) {
        $cnt=count($array);
        $i=1;
        while (List($key, $a) = each($array)) {
            if ($i==$cnt && $key < $k) 
                return(Array($key,false));
            if ($key > $k)
                if ($i==1 && $key < $k) {
                    return(Array(false, $key));
                } else {
                    return(Array($prev, $key));
                }
            $prev = $key;
            $i++;
        }
        return(Array($prev, $key));
    }

    /*
     * Interpolate data. 
     * $xy - array of array (x=>y)
     * $newx - array od new x
     * $const - use only constant values
     * Returns array of new Y
     */
    function interpolate($xy, $newx, $const=false) {
        $y=Array();
        $oldx=array_keys($xy);
        foreach ($newx as $x) {
                List($x0, $x1) = self::arr_closestkey($xy, $x);  
                $y0=false;
                $y1=false;
                if (!$x0) {
                    $y0=$xy[$x1];
                } else {
                    $y0=$xy[$x0];
                }
                if (!$x1) {
                    $y1=$xy[$x0];
                } else {
                    $y1=$xy[$x1];
                }
                if ($const) {
                    $y[$x]=$y0;
                } else {
                    $y[$x] = $y0 + ($x - $x0) * ($y1 - $y0) / ($x1 - $x0);
                }
        }
        return($y);
    }

}

?>
