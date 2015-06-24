<?php

namespace App\Model;

use ZabbixApi\ZabbixApi,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \Exception;

/**
 * Monda global class
 */
class Monda extends Nette\Object {
    
    private $zabbixlist;
    
    public function __construct() {
        
    }
    
    static function init_sql() {

        CliDebug::dbg("Using Zabbix db (".Options::get("zdsn").")\n");
        self::$zq = New Context(
                        New Nette\Database\Connection(
                                Options::get("zdsn"), Options::get("zdbuser"), Options::get("zdbpw"), array("lazy" => true))
                        );
        Monda::zlowpri();
        
        CliDebug::dbg("Using Monda db (".Options::get("mdsn").")\n");
        self::$mq = New Context(
                        New Nette\Database\Connection(
                                Options::get("mdsn"), Options::get("mdbuser"), Options::get("mdbpw"), array("lazy" => true))
                        );
        Monda::mlowpri();

        if (self::$zq && self::$mq) {
            return(true);
        } else {
            throw Exception("Cannot connect to monda or zabbix db");
        }
    }
    
    static function monda2zabbix($query,$table,$columns=false,$tmp="TEMPORARY") {
        if (is_string($query)) {
            $mq=self::mquery($query);
            if (!$mq) {
                return(false);
            }
        } else {
            $mq=$query;
        }
        $row=$mq->fetch();
        $createsql="CREATE $tmp TABLE $table (\n";
        $i=0;
        $l=count($row);
        foreach ($row as $c=>$v) {
            if (is_array($columns) && array_key_exists($c,$columns)) {
                $createsql .= "$c ".$columns[$c];
            } else {
                if (is_float($v)) {
                    $createsql .= "$c double precision ";
                } elseif (is_integer($v)) {
                    $createsql .= "$c bigint ";
                } else {
                    $createsql .= "$c character varying(255) ";
                }
            }
            $i++;
            if ($i<$l) $createsql .= ",\n"; else $createsql .= "\n";
        }
        $createsql .= ")";
        self::zquery($createsql);
        while ($row=$mq->fetch()) {
            self::zquery("INSERT INTO $table ?",$row);
        }
    }
    
    static function zlowpri() {
        $db=preg_split("/:/",Options::get("zdsn"));
        switch ($db[0]) {
            case "pgsql":
                try {
                    Monda::zquery("SELECT set_backend_priority(pg_backend_pid(), 19);");
                } catch (Exception $e) {
                    CliDebug::warn("Missing set_backend_priority extension on Zabbix DB.\n");  
                }
                break;
        }
    }
    
    static function mlowpri() {
        $db=preg_split("/:/",Options::get("mdsn"));
        switch ($db[0]) {
            case "pgsql":
                try {
                    Monda::mquery("SELECT set_backend_priority(pg_backend_pid(), 19);");
                } catch (Exception $e) {
                    CliDebug::warn("Missing set_backend_priority extension on Monda DB.\n");  
                }
                break;
        }
    }
    
    static function zbackends() {
        $db=preg_split("/:/",Options::get("zdsn"));
        switch ($db[0]) {
            case "pgsql":
               $ret=Monda::zquery("SELECT count(*) AS cnt
                    FROM pg_stat_activity WHERE current_query<>'<IDLE>'")->fetch()->cnt;
                break;
            default:
                $ret=0;
        }
        return($ret);
    }
    
    static function mbackends() {
        $db=preg_split("/:/",Options::get("mdsn"));
        switch ($db[0]) {
            case "pgsql":
               $ret=Monda::mquery("SELECT count(*) AS cnt
                    FROM pg_stat_activity WHERE current_query<>'<IDLE>'")->fetch()->cnt;
                break;
            default:
                $ret=0;
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
        if (Options::get("progress")) CliDebug::progress("M\r");
        $ret=self::$mq->queryArgs(array_shift($args),$args);
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function mcquery($query) {
        $args = func_get_args();
        $ckey=md5(serialize($args));
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null) {
            $ret=self::mquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => Options::get("sqlcacheexpire"),
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
        if (Options::get("dry")) {
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
    
    static function initJobServer() {
        CliDebug::warn(sprintf("Initializing job server. Maximum childs=%d.\n",Options::get("fork")));
        $stat=self::systemStats(3);
        unset(self::$zq);
        unset(self::$mq);
        unset(self::$api);
        self::$zq=null;
        self::$mq=null;
        self::$api=null;
    }
    
    static function exitJobServer() {
        if (self::$childs>0) {
            CliDebug::warn(sprintf("Stopping job server. Waiting for %d childs.\n",self::$childs));
        }
        while (self::$childs>0) {
            CliDebug::info(sprintf("Waiting for %d childs.\n",self::$childs));
            self::waitForChilds(true);
            sleep(1);
        }
    }

    static function doJob() {
        
        if (!Options::get("fork")) {
            return(true);
        }
        
        if (!self::$jobstats) {
            self::initJobServer();
        }
        if (isset(Options::get("maxload"))) {
            List($min1,$min5,$min15)=  sys_getloadavg();
            while ($min1>Options::get("maxload")) {
                CliDebug::warn(sprintf("Waiting for lower loadavg (actual=%f,max=%f)\n",$min1,Options::get("maxload")));
                self::systemStats(5);
                List($min1,$min5,$min15)=  sys_getloadavg();
            }
        }
        /* if (isset(Options::get("maxbackends"))) {
            $cnt=self::zbackends();
            $cnt2=self::mbackends();
            while ($cnt>Options::get("maxbackends") || $cnt2>Options::get("maxbackends")) {
                CliDebug::warn(sprintf("Waiting for lower number of psql backends (actual=[zabbix=%d,monda=%d],max=%d)\n",$cnt,$cnt2,Options::get("maxbackends")));
                $stat=self::systemStats(10);
                $cnt= self::zbackends();
                $cnt2=self::mbackends();
            }
        } */
        if (isset(Options::get("maxcpuwait"))) {
            while (self::$jobstats["iowait"]>Options::get("maxcpuwait")) {
                CliDebug::warn(sprintf("Waiting for lower iowait (actual=%f,max=%f)\n",self::$jobstats["iowait"],Options::get("maxcpuwait")));
                self::systemStats(5);
            }
        }
        
        if (!function_exists('pcntl_fork')
                || !function_exists('pcntl_wait')
                || !function_exists('pcntl_wifexited')) {
            CliDebug::warn("pcntl_* functions disabled, cannot fork!\n");
            return(true);
        }
        if (self::$childs<Options::get("fork")) {
            $pid=pcntl_fork();
            if ($pid==-1) {
                mexit(3,"Cannot fork");
            } else {
                if ($pid) {
                    self::$childpids[]=$pid;
                    self::$childs++;
                    //CliDebug::info("Jobserver: Parent (childs=self::$childs)\n");
                    return(false);
                } else {
                    putenv("MONDA_CHILD=1");
                    //CliDebug::info("Jobserver: Child (childs=self::$childs)\n");
                    return(true);
                }
            }
        } else {
            self::waitForChilds();
        }
    }
    
    static function waitForChilds($end=false) {
        if (!$end) {
            $maxchilds=Options::get("fork");
        } else {
            $maxchilds=0;
        }
        while (self::$childs>=$maxchilds) {
            CliDebug::info("Jobserver: Waiting for childs (childs=self::$childs)\n");
            $s=false;
            $pid=pcntl_wait($s,WNOHANG);
            $status=pcntl_wexitstatus($s);
            while (!$pid) {
                self::systemStats(2);
                $pid=pcntl_wait($s,WNOHANG);
                $status=pcntl_wexitstatus($s);
            }

            if ($status==0) {
                self::$childs--;
                CliDebug::info("Jobserver: Child exited (childs=self::$childs)\n");
                return(false);
            } else {
                foreach (self::$childpids as $pid) {
                    posix_kill($pid,SIGTERM);
                }
                \App\Presenters\BasePresenter::mexit(2,"One child died! Exiting!\n");
            }
        }
    }
    
    static function exitJob() {
        if (getenv("MONDA_CHILD")) {
            CliDebug::info("Jobserver: Exit child.\n");
            unset(self::$zq);
            unset(self::$mq);
            unset(self::$api);
            for ($i=3;$i<100;$i++) {
                @fclose($i);
            }
            \App\Presenters\DefaultPresenter::mexit();
        }
    }

}

