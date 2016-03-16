<?php

namespace App\Model;

use ZabbixApi\ZabbixApi,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Tracy\Debugger,
    Nette\Database\Context,
    Nette\Database\Connection,
    \Exception;

/**
 * Monda global class
 */
class Monda extends Nette\Object {
    
    const _1HOUR=3600;
    const _1DAY=86400;
    const _1WEEK=604800;
    const _1MONTH=2505600;
    const _1MONTH28=2419200;
    const _1MONTH30=2592000;
    const _1MONTH31=2678400;
    const _1YEAR=31536000;
    static $cache; // Cache
    static $apicache; // Cache for zabbix api
    static $sqlcache;
    static $api;   // ZabbixApi class
    static $zq;    // Zabbix query link id
    static $mq;    // Monda query link id
    static $lastsql;

    static function init_api() {
        if (Opts::getOpt("zapi") && !Opts::getOpt("help")) {
            if (Opts::getOpt("zapiurl") && Opts::getOpt("zapiuser") && Opts::getOpt("zapipw")) {
                CliDebug::dbg("Initialising Zabbix API\n", Debugger::DEBUG);
                try {
                    self::$api = new ZabbixApi(Opts::getOpt("zapiurl"), Opts::getOpt("zapiuser"), Opts::getOpt("zapipw"));
                } catch (Exception $e) {
                    CliDebug::log($e,Debugger::ERROR);
                }
            } else {
                throw New \Exception("Undefined parameters!");
            }
            return(self::$api);
        } else {
            return(false);
        }
    }

    static function init_sql() {
        CliDebug::dbg("Using Zabbix db (".Opts::getOpt("zdsn").")\n");
        $c=New Connection(
                Opts::getOpt("zdsn"),
                Opts::getOpt("zdbuser"),
                Opts::getOpt("zdbpw"),
                array("lazy" => true)
                );
        self::$zq = New Context(
                $c,
                New Nette\Database\Structure($c, New Nette\Caching\Storages\FileStorage(getenv("MONDA_SQLCACHEDIR")))
                );
        
        CliDebug::dbg("Using Monda db (".Opts::getOpt("mdsn").")\n");
        $c=New Connection(
                Opts::getOpt("mdsn"),
                Opts::getOpt("mdbuser"),
                Opts::getOpt("mdbpw"),
                array("lazy" => true)
                );
        self::$mq = New Context(
                $c,
                New Nette\Database\Structure($c, New Nette\Caching\Storages\FileStorage(getenv("MONDA_SQLCACHEDIR")))
                );

        if (self::$zq && self::$mq) {
            return(true);
        } else {
            throw Exception("Cannot connect to monda or zabbix db");
        }
    }
    
    static function apiCmd($cmd,$req) {
        $ckey=$cmd.serialize($req);
        $ret = self::$apicache->load($ckey);
        if ($ret === NULL || Opts::getOpt("apicacheexpire")==0) {
            if (!isset(self::$api)) {
                if (!self::init_api()) {
                    CliDebug::warn("Zabbix Api query ignored (zapi=false)! ($cmd)\n");
                    return(Array());
                }
            }
            CliDebug::dbg("Zabbix Api query ($cmd)\n");
            $ret = self::$api->$cmd($req);
            self::$apicache->save($ckey, $ret, array(
                Nette\Caching\Cache::EXPIRE => Opts::getOpt("apicacheexpire"),
            ));
        }
        return($ret);
    }
    
    static function zquery($query) {
        if (!self::$zq) {
             Monda::init_sql();
        }
        if (!is_array($query)) {
            $args = func_get_args();
        } else {
            $args=$query;
        }
        $psql=new \Nette\Database\SqlPreprocessor(self::$zq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("zquery(\n$sql\n)=\n");
        $ret=self::$zq->queryArgs(array_shift($args),$args);
        CliDebug::dbg(sprintf("%d\n",count($ret)));
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function zcquery($query) {
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null || Opts::getOpt("sqlcacheexpire")==0) {
            $ret=self::zquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => Opts::getOpt("sqlcacheexpire"),
                        )
                    );
        }
        return($ret);
    }
   
    static function mquery($query) {
        if (!self::$mq) {
             Monda::init_sql();
        }
        if (!is_array($query)) {
            $args = func_get_args();
        } else {
            $args=$query;
        }
        $psql=new \Nette\Database\SqlPreprocessor(self::$mq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("mquery(\n$sql\n)\n");
        $ret=self::$mq->queryArgs(array_shift($args),$args);
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function mcquery($query) {
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null || Opts::getOpt("sqlcacheexpire")==0) {
            $ret=self::mquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => Opts::getOpt("sqlcacheexpire"),
                        )
                    );
        }
        return($ret);
    }
    
    static function mbegin() {
        if (!self::$mq || !self::$mq->getConnection()->getPdo()) {
             Monda::init_sql();
        }
        self::$mq->beginTransaction();
    }
    
    static function mcommit() {
        if (Opts::getOpt("dry")) {
            CliDebug::warn("Rolling back changes. Dry run requested!\n");
            self::$mq->rollBack();
        } else {
            CliDebug::dbg("Commiting changes\n");
            self::$mq->commit();
        }
    }
    
    static function extractIds($array,$keys) {
        $ret=Array();
        foreach ($keys as $k) {
            foreach ($array as $a) {
                if (array_key_exists($k,$a)) {
                    $ret[$k][]=$a[$k];
                }
            }
            $ret[$k]=array_unique($ret[$k]);
        }
        return($ret);
    }
    
    static function IdsSearch($ids,$array) {
        foreach ($array as $a) {
            $ret=true;
            foreach ($ids as $key=>$value) {
                if (!(array_key_exists($key,$a) && $a[$key]==$value)) {
                    $ret=false;
                }
            }
            if ($ret) return(true);
        }
        return(false);
    }

    static function historyinfo() {
        $hi = Monda::zquery("SELECT min(clock),max(clock),COUNT(*)
                FROM history
                GROUP BY itemid
                WHERE clock>?",
                time() - Opts::getOpt("start"));
    }
    
    static function sadd($key) {
        if (array_key_exists($key,self::$stats)) {
            self::$stats[$key]++;
        } else {
            self::$stats[$key]=0;
        }
    }
    
    static function sreset() {
        self::$stats=Array();
    }
    
    static function sget($stat=false) {
        if (!$stat) {
            return(self::$stats);
        }
        if (array_key_exists($stat,self::$stats)) {
            return(self::$stats[$stat]);
        } else {
            return(0);
        }
    }
    
    static function statinfo() {
        foreach (self::$stats as $key=>$value) {
            if (!preg_match("/\./",$key)) {
                echo "$key:$value,";
            }
        }
        echo "\n";
    }
    
    static function profile($msg="") {
        if (!self::$lastns) {
            self::$lastns=microtime(true);
        } else {
            echo sprintf("$msg%.2f\n",microtime(true)-self::$lastns);
            self::$lastns=microtime(true);
        }
    }
    
    static function systemStats($secs=false) {
        if ($secs || !isset(self::$cpustatsstamp)) {
            if (!$secs) $secs=1;
            CliDebug::dbg("Collecting system stats for $secs seconds\n");
            $stat1 = file('/proc/stat');
            $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0]));
            sleep($secs);
        } else {
            if (microtime(true)-self::$cpustatsstamp<1) {
                return(Monda::systemStats(1));
            } else {
                CliDebug::dbg(sprintf("Collected system stats for last %.2f seconds\n",microtime(true)-self::$cpustatsstamp));                
            }
            $info1 = self::$cpustats;
        }
        $stat2 = file('/proc/stat');
        $info2 = explode(" ", preg_replace("!cpu +!", "", $stat2[0]));
        
        $dif = array();
        $dif['user'] = $info2[0] - $info1[0];
        $dif['nice'] = $info2[1] - $info1[1];
        $dif['sys'] = $info2[2] - $info1[2];
        $dif['idle'] = $info2[3] - $info1[3];
        $dif['iowait'] = $info2[4] - $info1[4];
        $total = array_sum($dif);
        $cpu = array();
        foreach ($dif as $x => $y)
            $cpu[$x] = round($y / $total * 100, 1);
        if (microtime(true)-self::$cpustatsstamp>1) {
            self::$cpustats=$info2;
            self::$cpustatsstamp=microtime(true);
        }
        self::$jobstats=$cpu;
        return($cpu);
    }
 
}

