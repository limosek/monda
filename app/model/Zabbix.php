<?php
namespace App\Model;

use Nette,
    Tracy\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * ItemStat global class
 */
class Zabbix extends Nette\Object {
    
    private $zabbixid;
    private $url;
    private $dsn;
    private $sqluser;
    private $sqlpass;
    private $apiuser;
    private $apipass;
    private $apiurl;
    private $api;
    
    static function apiInit() {
        if (Options::get("zapi") && !Options::get("help")) {
            if (Options::get("zapiurl") && Options::get("zapiuser") && Options::get("zapipw")) {
                Debugger::log("Initialising Zabbix API\n", Debugger::DEBUG);
                try {
                    self::$api = new ZabbixApi(Options::get("zapiurl"), Options::get("zapiuser"), Options::get("zapipw"));
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
    
    function apiCmd($cmd,$req) {
        $ckey=md5($cmd.serialize($req));
        $ret = self::$apicache->load($ckey);
        if ($ret === NULL) {
            if (!isset(self::$api)) {
                if (!self::init_api()) {
                    CliDebug::warn("Zabbix Api query ignored (zapi=false)! ($cmd)\n");
                    return(Array());
                }
            }
            if (Options::get("progress")) CliDebug::progress("A\r");
            CliDebug::dbg("Zabbix Api query ($cmd)\n");
            $ret = self::$api->$cmd($req);
            self::$apicache->save($ckey, $ret, array(
                Nette\Caching\Cache::EXPIRE => Options::get("apicacheexpire"),
            ));
        }
        return($ret);
    }
    
    static function query($query) {
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
        if (Options::get("progress")) CliDebug::progress("Z\r");
        $ret=self::$zq->queryArgs(array_shift($args),$args);
        CliDebug::dbg(sprintf("%d\n",count($ret)));
        self::$lastsql=$sql;
        return($ret);
    }
    
    static function cquery($query) {
        $args = func_get_args();
        $ckey=md5(serialize($args));
        $ret=self::$sqlcache->load($ckey);
        if ($ret===null) {
            $ret=self::zquery($args)->fetchAll();
            self::$sqlcache->save($ckey,
                    $ret,
                    array(
                        Nette\Caching\Cache::EXPIRE => Options::get("sqlcacheexpire"),
                        )
                    );
        }
        return($ret);
    }
    
    static function zabbix2monda($query,$table,$columns=false,$tmp="TEMPORARY") {
        if (is_string($query)) {
            self::mbegin();
            $zq=self::zquery($query);
            if (!$zq) {
                return(false);
            }
        } else {
            $zq=$query;
        }
        $row=$zq->fetch();
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
        self::mquery($createsql);
        while ($row=$zq->fetch()) {
            self::mquery("INSERT INTO $table ?",$row);
        }
        self::mcommit();
    }
    
   public function __construct($zabbixid) {
       
   }
   
}

?>
