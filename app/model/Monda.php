<?php

namespace App\Model;

use \ZabbixApi,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \Exception;

/**
 * Monda global class
 */
class Monda extends Nette\Object {
    
    public static $debuglevel;
    const _1HOUR=3600;
    const _1DAY=86400;
    const _1WEEK=604800;
    const _1MONTH=2678400;
    const _1YEAR=31536000;

    function init_api() {
        if ($this->opts->zapi && !$this->opts->help) {
            if ($this->opts->zapiurl && $this->opts->zapiuser && $this->opts->zapipw) {
                Debugger::log("Initialising Zabbix API\n", Debugger::DEBUG);
                try {
                    $this->api = new ZabbixApi($this->opts->zapiurl, $this->opts->zapiuser, $this->opts->zapipw);
                } catch (Exception $e) {
                    CliDebug::log($e,Debugger::ERROR);
                }
            } else {
                throw New \Exception("Undefined parameters!");
            }
            return($this->api);
        } else {
            return(false);
        }
    }

    function init_sql() {

        CliDebug::dbg("Using Zabbix db (".$this->opts->zdsn.")\n");
        $this->zq = New Context(
                        New Nette\Database\Connection(
                                $this->opts->zdsn, $this->opts->zdbuser, $this->opts->zdbpw, array("lazy" => true))
                        );
        Monda::zlowpri();
        
        CliDebug::dbg("Using Monda db (".$this->opts->mdsn.")\n");
        $this->mq = New Context(
                        New Nette\Database\Connection(
                                $this->opts->mdsn, $this->opts->mdbuser, $this->opts->mdbpw, array("lazy" => true))
                        );
        Monda::mlowpri();

        if ($this->zq && $this->mq) {
            return(true);
        } else {
            throw Exception("Cannot connect to monda or zabbix db");
        }
    }

    function apiCmd($cmd,$req) {
        $ckey=$cmd.serialize($req);
        $ret = $this->apicache->load($ckey);
        if ($ret === NULL) {
            if (!isset($this->api)) {
                if (!self::init_api()) {
                    CliDebug::warn("Zabbix Api query ignored (zapi=false)! ($cmd)\n");
                    return(Array());
                }
            }
            CliDebug::dbg("Zabbix Api query ($cmd)\n");
            $ret = $this->api->$cmd($req);
            $this->apicache->save($ckey, $ret, array(
                Nette\Caching\Cache::EXPIRE => $this->opts->apicacheexpire,
            ));
        }
        return($ret);
    }
    
    function zquery($query) {
        if (!$this->zq) {
             Monda::init_sql();
        }
        if (!is_array($query)) {
            $args = func_get_args();
        } else {
            $args=$query;
        }
        $psql=new \Nette\Database\SqlPreprocessor($this->zq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("zquery(\n$sql\n)=\n");
        $ret=$this->zq->queryArgs(array_shift($args),$args);
        CliDebug::dbg(sprintf("%d\n",count($ret)));
        $this->lastsql=$sql;
        return($ret);
    }
    
    function zcquery($query) {
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=$this->sqlcache->load($ckey);
        if ($ret===null) {
            $ret=self::zquery($args)->fetchAll();
            $this->sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => $this->opts->sqlcacheexpire,
                        )
                    );
        }
        return($ret);
    }
    
    function zlowpri() {
        $db=preg_split("/:/",$this->opts->zdsn);
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
    
    function mlowpri() {
        $db=preg_split("/:/",$this->opts->mdsn);
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
    
    function zbackends() {
        $db=preg_split("/:/",$this->opts->zdsn);
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
    
    function mbackends() {
        $db=preg_split("/:/",$this->opts->mdsn);
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
    
    function mquery($query) {
        if (!$this->mq) {
             Monda::init_sql();
        }
        if (!is_array($query)) {
            $args = func_get_args();
        } else {
            $args=$query;
        }
        $psql=new \Nette\Database\SqlPreprocessor($this->mq->connection);
        List($sql)=$psql->process($args);
        CliDebug::dbg("mquery(\n$sql\n)\n");
        $ret=$this->mq->queryArgs(array_shift($args),$args);
        $this->lastsql=$sql;
        return($ret);
    }
    
    function mcquery($query) {
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=$this->sqlcache->load($ckey);
        if ($ret===null) {
            $ret=self::mquery($args)->fetchAll();
            $this->sqlcache->save(
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => $this->opts->sqlcacheexpire,
                        )
                    );
        }
        return($ret);
    }
    
    function mbegin() {
        if (!$this->mq || !$this->mq->getConnection()->getPdo()) {
             Monda::init_sql();
        }
        $this->mq->beginTransaction();
    }
    
    function mcommit() {
        if ($this->opts->dry) {
            CliDebug::warn("Rolling back changes. Dry run requested!\n");
            $this->mq->rollBack();
        } else {
            CliDebug::dbg("Commiting changes\n");
            $this->mq->commit();
        }
    }
    
    function extractIds($array,$keys) {
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
    
    function IdsSearch($ids,$array) {
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

    function historyinfo() {
        $hi = Monda::zquery("SELECT min(clock),max(clock),COUNT(*)
                FROM history
                GROUP BY itemid
                WHERE clock>?",
                time() - $this->opts->start);
    }
    
    function sadd($key) {
        if (array_key_exists($key,$this->stats)) {
            $this->stats[$key]++;
        } else {
            $this->stats[$key]=0;
        }
    }
    
    function sreset() {
        $this->stats=Array();
    }
    
    function sget($stat=false) {
        if (!$stat) {
            return($this->stats);
        }
        if (array_key_exists($stat,$this->stats)) {
            return($this->stats[$stat]);
        } else {
            return(0);
        }
    }
    
    function statinfo() {
        foreach ($this->stats as $key=>$value) {
            if (!preg_match("/\./",$key)) {
                echo "$key:$value,";
            }
        }
        echo "\n";
    }
    
    function profile($msg="") {
        if (!$this->lastns) {
            $this->lastns=microtime(true);
        } else {
            echo sprintf("$msg%.2f\n",microtime(true)-$this->lastns);
            $this->lastns=microtime(true);
        }
    }
    
    function systemStats($secs=false) {
        if ($secs || !isset($this->cpustatsstamp)) {
            if (!$secs) $secs=1;
            CliDebug::dbg("Collecting system stats for $secs seconds\n");
            $stat1 = file('/proc/stat');
            $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0]));
            sleep($secs);
        } else {
            if (microtime(true)-$this->cpustatsstamp<1) {
                return(Monda::systemStats(1));
            } else {
                CliDebug::dbg(sprintf("Collected system stats for last %.2f seconds\n",microtime(true)-$this->cpustatsstamp));                
            }
            $info1 = $this->cpustats;
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
        if (microtime(true)-$this->cpustatsstamp>1) {
            $this->cpustats=$info2;
            $this->cpustatsstamp=microtime(true);
        }
        $this->jobstats=$cpu;
        return($cpu);
    }
    
    function initJobServer() {
        CliDebug::warn(sprintf("Initializing job server. Maximum childs=%d.\n",$this->opts->fork));
        $stat=self::systemStats(3);
        unset($this->zq);
        unset($this->mq);
        unset($this->api);
        $this->zq=null;
        $this->mq=null;
        $this->api=null;
    }
    
    function exitJobServer() {
        if ($this->childs>0) {
            CliDebug::warn(sprintf("Stopping job server. Waiting for %d childs.\n",$this->childs));
        }
        while ($this->childs>0) {
            CliDebug::info(sprintf("Waiting for %d childs.\n",$this->childs));
            self::waitForChilds(true);
            sleep(1);
        }
    }

    function doJob() {
        
        if (!$this->opts->fork) {
            return(true);
        }
        
        if (!$this->jobstats) {
            self::initJobServer();
        }
        if (isset($this->opts->maxload)) {
            List($min1,$min5,$min15)=  sys_getloadavg();
            while ($min1>$this->opts->maxload) {
                CliDebug::warn(sprintf("Waiting for lower loadavg (actual=%f,max=%f)\n",$min1,$this->opts->maxload));
                self::systemStats(5);
                List($min1,$min5,$min15)=  sys_getloadavg();
            }
        }
        /* if (isset($this->opts->maxbackends)) {
            $cnt=self::zbackends();
            $cnt2=self::mbackends();
            while ($cnt>$this->opts->maxbackends || $cnt2>$this->opts->maxbackends) {
                CliDebug::warn(sprintf("Waiting for lower number of psql backends (actual=[zabbix=%d,monda=%d],max=%d)\n",$cnt,$cnt2,$this->opts->maxbackends));
                $stat=self::systemStats(10);
                $cnt= self::zbackends();
                $cnt2=self::mbackends();
            }
        } */
        if (isset($this->opts->maxcpuwait)) {
            while ($this->jobstats["iowait"]>$this->opts->maxcpuwait) {
                CliDebug::warn(sprintf("Waiting for lower iowait (actual=%f,max=%f)\n",$this->jobstats["iowait"],$this->opts->maxcpuwait));
                self::systemStats(5);
            }
        }
        
        if (!function_exists('pcntl_fork')
                || !function_exists('pcntl_wait')
                || !function_exists('pcntl_wifexited')) {
            CliDebug::warn("pcntl_* functions disabled, cannot fork!\n");
            return(true);
        }
        if ($this->childs<$this->opts->fork) {
            $pid=pcntl_fork();
            if ($pid==-1) {
                mexit(3,"Cannot fork");
            } else {
                if ($pid) {
                    $this->childpids[]=$pid;
                    $this->childs++;
                    //CliDebug::info("Jobserver: Parent (childs=$this->childs)\n");
                    return(false);
                } else {
                    putenv("MONDA_CHILD=1");
                    //CliDebug::info("Jobserver: Child (childs=$this->childs)\n");
                    return(true);
                }
            }
        } else {
            self::waitForChilds();
        }
    }
    
    function waitForChilds($end=false) {
        if (!$end) {
            $maxchilds=$this->opts->fork;
        } else {
            $maxchilds=0;
        }
        while ($this->childs>=$maxchilds) {
            CliDebug::info("Jobserver: Waiting for childs (childs=$this->childs)\n");
            $s=false;
            $pid=pcntl_wait($s,WNOHANG);
            $status=pcntl_wexitstatus($s);
            while (!$pid) {
                self::systemStats(2);
                $pid=pcntl_wait($s,WNOHANG);
                $status=pcntl_wexitstatus($s);
            }

            if ($status==0) {
                $this->childs--;
                CliDebug::info("Jobserver: Child exited (childs=$this->childs)\n");
                return(false);
            } else {
                foreach ($this->childpids as $pid) {
                    posix_kill($pid,SIGTERM);
                }
                \App\Presenters\BasePresenter::mexit(2,"One child died! Exiting!\n");
            }
        }
    }
    
    function exitJob() {
        if (getenv("MONDA_CHILD")) {
            CliDebug::info("Jobserver: Exit child.\n");
            unset($this->zq);
            unset($this->mq);
            unset($this->api);
            for ($i=3;$i<100;$i++) {
                @fclose($i);
            }
            \App\Presenters\DefaultPresenter::mexit();
        }
    }

}

