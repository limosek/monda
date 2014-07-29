<?php

namespace App\Model;

use \Exception,Nette,
    Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * Monda global class
 */
class Monda extends Nette\Object {

    public $cache; // Cache
    public $apicache; // Cache for zabbix api
    public $sqlcache;
    private $api;   // ZabbixApi class
    private $zq;    // Zabbix query link id
    private $mq;    // Monda query link id
    public $dbg;    // Cli debugger
    private $zabbix_url;
    private $zabbix_user;
    private $zabbix_pw;
    private $zabbix_db_type;
    private $stats=Array();
    private $lastns=false;
    public $opts;
    public $cpustats;
    public $cpustatsstamp;
    public $childpids=Array();
    public $childs;

    function init_api() {
        if (isset($this->opts->noapi) && !$this->opts->noapi) {
            if ($this->opts->zapiurl && $this->opts->zapiuser && isset($this->opts->zapipw)) {
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

        $this->dbg->dbg("Using Zabbix db (".$this->opts->zdsn.")\n");
        $this->zq = New Context(
                        New Nette\Database\Connection(
                                $this->opts->zdsn, $this->opts->zdbuser, $this->opts->zdbpw, array("lazy" => true))
                        );
        $this->zlowpri();
        
        $this->dbg->dbg("Using Monda db (".$this->opts->mdsn.")\n");
        $this->mq = New Context(
                        New Nette\Database\Connection(
                                $this->opts->mdsn, $this->opts->mdbuser, $this->opts->mdbpw, array("lazy" => true))
                        );
        $this->mlowpri();

        if ($this->zq && $this->mq) {
            return(true);
        } else {
            throw Exception("Cannot connect to monda or zabbix db");
        }
    }

    function __construct($opts) {
        global $container;

        $c = $container;
        $this->opts=$opts;
        $this->dbg=New CliDebug();
        $this->apicache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage('temp/cache/api'));
        $this->sqlcache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage('temp/cache/sql'));
        $this->cache = New Nette\Caching\Cache(
                    New Nette\Caching\Storages\FileStorage('temp/cache'));
    }
    
    function apiCmd($cmd,$req) {
        $ckey=$cmd.serialize($req);
        $ret = $this->apicache->load($ckey);
        if ($ret === NULL) {
            if (!isset($this->api)) {
                if (!$this->init_api()) {
                    return(false);
                }
            }
            $this->dbg->dbg("Zabbix Api query ($cmd)\n");
            $ret = $this->api->$cmd($req);
            $this->apicache->save($ckey, $ret, array(
                Nette\Caching\Cache::EXPIRE => '24 hours',
            ));
        }
        return($ret);
    }
    
    function zquery($query) {
        if (!$this->zq) {
             $this->init_sql();
        }
        $args = func_get_args();
        $psql=new \Nette\Database\SqlPreprocessor($this->zq->connection);
        List($sql)=$psql->process($args);
        $this->dbg->dbg("zquery($sql)\n");
        $ret=$this->zq->queryArgs(array_shift($args),$args);
        return($ret);
    }
    
    function zcquery($query) {
        if (!$this->zq) {
             $this->init_sql();
        }
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=$this->sqlcache->load($ckey);
        if ($ret===null) {
            $psql=new \Nette\Database\SqlPreprocessor($this->zq->connection);
            List($sql)=$psql->process($args);
            $this->dbg->dbg("zcquery($sql)\n");
            $ret=$this->zq->queryArgs(array_shift($args),$args)->fetchAll();
            $this->sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => '24 hours',
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
                    $this->zquery("SELECT set_backend_priority(pg_backend_pid(), 19);");
                } catch (Exception $e) {
                    $this->dbg->warn("Missing set_backend_priority extension on Zabbix DB.\n");  
                }
                break;
        }
    }
    
    function mlowpri() {
        $db=preg_split("/:/",$this->opts->mdsn);
        switch ($db[0]) {
            case "pgsql":
                try {
                    $this->mquery("SELECT set_backend_priority(pg_backend_pid(), 19);");
                } catch (Exception $e) {
                    $this->dbg->warn("Missing set_backend_priority extension on Monda DB.\n");  
                }
                break;
        }
    }
    
    function zbackends() {
        $db=preg_split("/:/",$this->opts->zdsn);
        switch ($db[0]) {
            case "pgsql":
               $ret=$this->zquery("SELECT count(*) AS cnt
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
               $ret=$this->mquery("SELECT count(*) AS cnt
                    FROM pg_stat_activity WHERE current_query<>'<IDLE>'")->fetch()->cnt;
                break;
            default:
                $ret=0;
        }
        return($ret);
    }
    
    function mquery($query) {
        if (!$this->mq) {
             $this->init_sql();
        }
        $args = func_get_args();
        $psql=new \Nette\Database\SqlPreprocessor($this->mq->connection);
        List($sql)=$psql->process($args);
        $this->dbg->dbg("mquery($sql)\n");
        $ret=$this->mq->queryArgs(array_shift($args),$args);
        return($ret);
    }
    
    function mcquery($query) {
        if (!$this->mq) {
             $this->init_sql();
        }
        $args = func_get_args();
        $ckey=serialize($args);
        $ret=$this->sqlcache->load($ckey);
        if ($ret===null) {
            $this->dbg->dbg("mcquery($ckey)\n");
            $ret=$this->mq->query(array_shift($args), $args)->fetchAll();
            $this->sqlcache->save(
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => '24 hours',
                        )
                    );
        }
        return($ret);
    }
    
    function mbegin() {
        if (!$this->mq) {
             $this->init_sql();
        }
        $this->mq->beginTransaction();
    }
    
    function mcommit() {
        if ($this->opts->dry) {
            $this->dbg->warn("Rolling back changes. Dry run requested!\n");
            $this->mq->rollBack();
        } else {
            $this->dbg->dbg("Commiting changes\n");
            $this->mq->commit();
        }
    }

    function historyinfo() {
        $hi = $this->zquery("SELECT min(clock),max(clock),COUNT(*)
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
            $this->dbg->dbg("Collecting system stats for $secs seconds\n");
            $stat1 = file('/proc/stat');
            $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0]));
            sleep($secs);
        } else {
            if (microtime(true)-$this->cpustatsstamp<1) {
                return($this->systemStats(1));
            } else {
                $this->dbg->dbg(sprintf("Collected system stats for last %.2f seconds\n",microtime(true)-$this->cpustatsstamp));                
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
        return($cpu);
    }

    function doJob() {
        
        $stat=$this->systemStats(2);
        if (isset($this->opts->maxload)) {
            List($min1,$min5,$min15)=  sys_getloadavg();
            while ($min1>$this->opts->maxload) {
                $this->dbg->warn(sprintf("Waiting for lower loadavg (actual=%f,max=%f)\n",$min1,$this->opts->maxload));
                $stat=$this->systemStats(10);
                List($min1,$min5,$min15)=  sys_getloadavg();
            }
        }
        /*if (isset($this->opts->maxbackends)) {
            $cnt=$this->zbackends();
            $cnt2=$this->mbackends();
            while ($cnt>$this->opts->maxbackends || $cnt2>$this->opts->maxbackends) {
                $this->dbg->warn(sprintf("Waiting for lower number of psql backends (actual=[zabbix=%d,monda=%d],max=%d)\n",$cnt,$cnt2,$this->opts->maxbackends));
                $stat=$this->systemStats(10);
                $cnt= $this->zbackends();
                $cnt2=$this->mbackends();
            }
        }*/
        if (isset($this->opts->maxcpuwait)) {
            while ($stat["iowait"]>$this->opts->maxcpuwait) {
                $this->dbg->warn(sprintf("Waiting for lower iowait (actual=%f,max=%f)\n",$stat["iowait"],$this->opts->maxcpuwait));
                $stat=$this->systemStats(10);
            }
        }
        if (!$this->opts->fork) {
            return(true);
        }
        if (!function_exists('pcntl_fork')
                || !function_exists('pcntl_wait')
                || !function_exists('pcntl_wifexited')) {
            $this->dbg->warn("pcntl_* functions disabled, cannot fork!\n");
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
                    $this->dbg->dbg("Parent (childs=$this->childs)\n");
                    return(false);
                } else {
                    putenv("MONDA_CHILD=1");
                    $this->mq=false;
                    $this->zq=false;
                    $this->init_sql();
                    $this->init_api();
                    return(true);
                }
            }
        } else {
            $s=false;
            while (pcntl_wait($s)) {
                $status=pcntl_wexitstatus($s);
                if (pcntl_wifexited($s) && $status==0) {
                    $this->childs--;
                    $this->dbg->dbg("Child exited (childs=$this->childs)\n");
                    return(false);
                } else {
                    foreach ($this->childpids as $pid) {
                        posix_kill($pid,SIGTERM);
                    }
                    BasePresenter::mexit(2,"One child died! Exiting!\n");
                }
            }
        }
    }
    
    function exitJob() {
        if (getenv("MONDA_CHILD")) {
            //$this->dbg->warn("Exit child.\n");
            \App\Presenters\DefaultPresenter::mexit();
        }
    }

}

