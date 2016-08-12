<?php

namespace App\Model;

use ZabbixApi\ZabbixApi,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Tracy\Debugger,
    Nette\Database\Context,
    Nette\Database\Structure,
    Nette\Database\Connection,
    Nette\Database\SqlPreprocessor,
    Nette\Caching\Storages\FileStorage,
    Nette\Caching\Cache,
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
    const _MAX_ROWS=1000;
    static $cache; // Cache
    static $apicache; // Cache for zabbix api
    static $sqlcache;
    static $api;   // ZabbixApi class
    static $zq;    // Zabbix query link id
    static $mq;    // Monda query link id
    static $profile;
    static $stats;
    static $lastsql;

    static function init_api() {
        if (Opts::getOpt("zabbix_api") && !Opts::getOpt("help")) {
            if (Opts::getOpt("zabbix_api_url") && Opts::getOpt("zabbix_api_user") && Opts::getOpt("zabbix_api_pw")) {
                CliDebug::dbg("Initialising Zabbix API\n", Debugger::DEBUG);
                try {
                    if (Opts::getOpt("api_profile")) self::profileStart("init_api");
                    self::$api = new ZabbixApi(Opts::getOpt("zabbix_api_url"), Opts::getOpt("zabbix_api_user"), Opts::getOpt("zabbix_api_pw"));
                    if (Opts::getOpt("api_profile")) self::profileEnd("init_api");
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                throw New Exception("Undefined parameters zabbix_api_url, zabbix_api_user, zabbix_api_pw?");
            }
            return(self::$api);
        } else {
            return(false);
        }
    }
    
    static function db_type($dsn) {
        preg_split("/;/",$dsn,$regs);
        return($regs[1]);
    }

    static function init_sql() {
        if (Opts::getOpt("sql_profile")) self::profileStart("init_sql");
        CliDebug::dbg("Using Zabbix db (".Opts::getOpt("zabbix_dsn").")\n");
        $c=New Connection(
                Opts::getOpt("zabbix_dsn"),
                Opts::getOpt("zabbix_db_user"),
                Opts::getOpt("zabbix_db_pw"),
                array("lazy" => true)
                );
        self::$zq = New Context(
                $c,
                New Structure($c, New FileStorage(getenv("MONDA_SQLCACHEDIR")))
                );
        CliDebug::dbg("Using Monda db (".Opts::getOpt("monda_dsn").")\n");
        $c=New Connection(
                Opts::getOpt("monda_dsn"),
                Opts::getOpt("monda_db_user"),
                Opts::getOpt("monda_db_pw"),
                array("lazy" => true)
                );
        self::$mq = New Context(
                $c,
                New Structure($c, New FileStorage(getenv("MONDA_SQLCACHEDIR")))
                );

        if (self::$zq && self::$mq) {
            CliDebug::info("Setting SQL timeout for Zabbix DB to ".Opts::getOpt("zabbix_db_query_timeout")." seconds.\n");
            if (self::db_type(Opts::getOpt("zabbix_dsn"))=="psql") {
                self::zquery("set statement_timeout=?",Opts::getOpt("zabbix_db_query_timeout")*1000);
            } elseif (self::db_type(Opts::getOpt("zabbix_dsn"))=="mysql") {
                self::zquery("SET STATEMENT max_statement_time=?",Opts::getOpt("zabbix_db_query_timeout"));
            }
            CliDebug::info("Setting SQL timeout for Monda DB to ".Opts::getOpt("monda_db_query_timeout")." seconds.\n");
            self::mquery("set statement_timeout=?",Opts::getOpt("monda_db_query_timeout")*1000);
            return(true);
        } else {
            throw Exception("Cannot connect to monda or zabbix db");
        }
        if (Opts::getOpt("sql_profile")) self::profileEnd("init_sql");
    }
    
    static function apiCmd($cmd,$req) {
        $ckey=$cmd.Opts::getOpt("zabbix_id").serialize($req);
        $ret = self::$apicache->load($ckey);
        if ($ret === NULL || Opts::getOpt("api_cache_expire")==0) {
            if (!isset(self::$api)) {
                if (!self::init_api()) {
                    CliDebug::warn("Zabbix Api query ignored (zapi=false)! ($cmd)\n");
                    return(Array());
                }
            }
            CliDebug::dbg("Zabbix Api query ($cmd)\n");
            try {
                if (Opts::getOpt("api_profile")) self::profileStart("api_cmd $cmd");
                $ret = self::$api->$cmd($req);
                if (Opts::getOpt("api_profile")) self::profileEnd("api_cmd $cmd");
            } catch (Exception $e) {
                throw $e;
            }
            if (count($ret)==0) {
                CliDebug::info("Zabbix API $cmd returned empty result. Check permissions:\n".print_r($req,true)."\n");
            }
            self::$apicache->save($ckey, $ret, array(
                Cache::EXPIRE => Opts::getOpt("api_cache_expire"),
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
        $psql=new SqlPreprocessor(self::$zq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("zquery(\n$sql\n)=\n");
        if (Opts::getOpt("sql_profile")) self::profileStart("zquery $sql");
        $ret=self::$zq->queryArgs(array_shift($args),$args);
        if (Opts::getOpt("sql_profile")) self::profileEnd("zquery $sql");
        CliDebug::dbg(sprintf("%d\n",count($ret)));
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function zcquery($query) {
        $args = func_get_args();
        $ckey=Opts::getOpt("zabbix_id").serialize($args);
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null || Opts::getOpt("sql_cache_expire")==0) {
            $ret=self::zquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Cache::EXPIRE => Opts::getOpt("sql_cache_expire"),
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
        $psql=new SqlPreprocessor(self::$mq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("mquery(\n$sql\n)\n");
        if (Opts::getOpt("sql_profile")) self::profileStart("mquery $sql");
        $ret=self::$mq->queryArgs(array_shift($args),$args);
        if (Opts::getOpt("sql_profile")) self::profileEnd("mquery $sql");
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function mcquery($query) {
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null || Opts::getOpt("sql_cache_expire")==0) {
            $ret=self::mquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Cache::EXPIRE => Opts::getOpt("sql_cache_expire"),
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
        if (array_key_exists($key,Monda::$stats)) {
            Monda::$stats[$key]++;
        } else {
            Monda::$stats[$key]=0;
        }
    }
    
    static function sreset() {
        Monda::$stats=Array();
    }
    
    static function sget($stat=false) {
        if (!$stat) {
            return(Monda::$stats);
        }
        if (array_key_exists($stat,Monda::$stats)) {
            return(Monda::$stats[$stat]);
        } else {
            return(0);
        }
    }
    
    static function statinfo() {
        foreach (Monda::$stats as $key=>$value) {
            if (!preg_match("/\./",$key)) {
                echo "$key:$value,";
            }
        }
        echo "\n";
    }
    
    static function profileStart($id) {
        $key=md5($id);
        self::$profile[$key] =Array(
            "last" => microtime(true),
            "id" => $id,
            "count" => 1,
            "S" =>0
        );
    }

    static function profileEnd($id) {
        $key=md5($id);
        self::$profile[$key]["S"]+= microtime(true)-self::$profile[$key]["last"];
        self::$profile[$key]["count"]++;
    }
    
    static function profileDump() {
        $out=print_r(self::$profile,true);
        foreach (self::$profile as $key=>$data) {
            $ms=(int) $data["S"]*1000;
            $count=$data["count"];
            $f=fopen(APP_DIR."/../out/profile/${ms}_${count}_monda.txt","w");
            fputs($f,$data["id"]);
            fclose($f);
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

