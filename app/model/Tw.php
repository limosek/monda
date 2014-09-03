<?php
namespace App\Model;

use Nette,
    Nette\Utils\Strings,
    Nette\Utils\DateTime as DateTime,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

/**
 * TimeWindow global class
 */
class Tw extends Monda {
    
    function twCreate($zid,$start,$length,$description) {
        
        $id=Monda::mquery("SELECT id FROM timewindow WHERE tfrom=? AND seconds IN (?) AND serverid=?",
                New DateTime("@$start"),
                $length,
                $zid
        )->fetch();
        if ($id==false) {
            CliDebug::warn("Creating window $start,$length,$description\n");
            return(
                Monda::mquery("INSERT INTO timewindow",Array(
                    "description" => $description,
                    "tfrom" => New DateTime("@$start"),
                    "seconds" => $length,
                    "created" => New DateTime(),
                    "serverid" => $zid,
                    "parentid" => null
            )));
        } else {
            CliDebug::dbg("Skiping window zabbix_id=$zid,start=$start,length=$length,$description (already in db)\n");
        }
    }
   
    function twMultiCreate($opts) {
        Monda::mbegin();
        foreach ($opts->length as $length) {
            for ($i=$opts->start;$i<$opts->end;$i=$i+$length) {
                if ($opts->startalign) {
                    switch ($length) {
                        case 3600:
                            $i=date_format(New DateTime(date("Y-m-d H:00",$i)),"U");
                            break;
                        case 3600*24:
                            $i=date_format(New DateTime(date("Y-m-d 00:00",$i)),"U");
                            break;
                        case 3600*24*7:
                            $i=date_format(New DateTime("@".strtotime("monday",$i)),"U");
                            break;
                        case 3600*24*31:
                            $i=date_format(New DateTime(date("Y-m-01 00:00",$i)),"U");
                            break;
                    }
                    if ($i<$opts->start || $i+$length>$opts->end) continue;
                }
                if ($opts->description) {
                    $desc=$opts->description;
                } else {
                    $desc=date("Y-m-d H:i",$i)."/$length";
                }
                Tw::twCreate($this->opts->zid,$i,$length,$desc);
            }
        }
        Monda::mcommit();
    }
    
    function twSearch($opts) {
        if ($opts->wids) {
            return(self::twGet($opts->wids));
        }
        $onlyemptysql="";
        if ($opts->empty) {
            $onlyemptysql = "(updated IS NULL OR COUNT(itemstat.itemid)=0) AND";
        } else {
            $onlyemptysql = "(updated IS NOT NULL AND COUNT(itemstat.itemid)>0) AND";
        }
        if (preg_match("#/#",$opts->wsort)) {
            List($sc,$so)=preg_split("#/#",$opts->wsort);
        } else {
            $sc=$opts->wsort;
            $so="+";
        }
        switch ($sc) {
            case "random":
                $sortsql="RANDOM()";
                break;
            case "start":
                $sortsql="tfrom";
                break;
            case "length":
                $sortsql="seconds";
                break;
            case "loi":
                $sortsql="loi";
                break;
            case "updated":
                $sortsql="updated";
                break;
            default:
                $sortsql="id";
        }
        if ($so=="-") { $sortsql.=" DESC"; }
        
        $updatedflag="true";
        if (is_numeric($opts->updated)) {
            $updatedflag=false;
            $updated=$opts->updated;
        } elseif ($opts->updated==false) {
            $updatedflag=true;
            $updated=0;
        } else {
            $updated=time();
        }
        if ($opts->createdonly) { 
            $createdsql="updated IS NULL";
        } else {
            $createdsql="true";
        }
        if ($opts->loionly) {
            if ($opts->loionly===true) {
                $loionlysql="timewindow.loi>0 AND timewindow.loi IS NOT NULL";
            } else {
                $loionlysql="timewindow.loi>$opts->loi AND timewindow.loi IS NOT NULL";
            }
            $updatedflag=true;
        } else {
            $loionlysql="true";
        }
        $rows = Monda::mquery("
            SELECT 
                id,parentid,
                tfrom,
                (tfrom+seconds*interval '1 second') AS tto,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                seconds,
                description,
                timewindow.loi,
                created,
                updated,
                found,
                processed,
                ignored,
                stddev0,
                lowavg,
                lowcnt,
                serverid,
                COUNT(itemstat.itemid) AS itemcount
            FROM timewindow
            LEFT JOIN itemstat ON (windowid=id)
            WHERE (serverid=? AND tfrom>=? AND (tfrom+seconds*interval '1 second')<=? AND seconds IN (?) AND (updated<? OR ?) AND $createdsql AND $loionlysql)
            GROUP BY id
            HAVING $onlyemptysql true
            ORDER BY $sortsql
                ",
                $opts->zid,
                New DateTime("@$opts->start"),
                New DateTime("@$opts->end"),
                $opts->length,
                New DateTime("@" . $updated),
                $updatedflag
               );
        return($rows);
    }
    
    function twSearchClock($clock) {
        $rows = Monda::mquery("
            SELECT 
                id,parentid,
                tfrom,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                seconds,
                description,
                loi,
                created,
                updated,
                found,
                processed,
                ignored,
                stddev0,
                lowavg,
                lowcnt,
                serverid
            FROM timewindow
            WHERE extract(epoch from tfrom)<? AND extract(epoch from tfrom)+seconds>?
            ",$clock,$clock);
        return($rows);
    }
    
    function twGet($wid) {
        $id=Monda::mquery("
            SELECT id,parentid,
                tfrom,
                extract(epoch from tfrom) AS fstamp,
                extract(epoch from tfrom)+seconds AS tstamp,
                seconds,
                description,
                timewindow.loi,
                created,
                updated,
                found,
                processed,
                ignored,
                stddev0,
                lowavg,
                lowcnt,
                serverid,
                COUNT(itemstat.itemid) AS itemcount
             FROM timewindow
             LEFT JOIN itemstat ON (windowid=id)
             WHERE id IN (?)
             GROUP BY timewindow.id",$wid);
        return($id);
    }
    
    function twToIds($opts) {
        $widrows=Tw::twSearch($opts);
        $wids=Array();
        while ($wid=$widrows->fetch()) {
            $wids[]=$wid->id;
        }
        return($wids);
    }
    
    function twStats($opts) {
        $row=Monda::mquery("
            SELECT
                MIN(tfrom) AS mintfrom,
                MAX(tfrom) AS maxtfrom,
                MIN(seconds) AS minlength,
                MIN(found) AS minfound,
                MAX(found) AS maxfound,
                MIN(stddev0) AS minstddev0,
                MAX(stddev0) AS maxstddev0,
                MIN(processed) AS minprocessed,
                MAX(processed) AS maxprocessed,
                MIN(ignored) AS minignored,
                MAX(ignored) AS maxignored,
                MIN(loi) AS minloi,
                MAX(loi) AS maxloi,
                STDDEV(loi) AS stddevloi
            FROM timewindow
            WHERE serverid=? AND updated IS NOT NULL AND processed>0",$opts->zid);
        return($row->fetchAll());
    }
    
    function twLoi($opts) {
        $opts->empty=false;
        $opts->updated=true;
        $wids=self::twToIds($opts);
        CliDebug::warn(sprintf("Recomputing loi for %d windows\n",count($wids)));
        if (count($wids)==0) {
            return(false);
        }
        Monda::mbegin();
        $uloi=Monda::mquery("
            UPDATE timewindow twchild
            SET loi=round(100*(processed::float/found::float)),
            parentid=( SELECT id from timewindow twparent
              WHERE twchild.tfrom>=twparent.tfrom
              AND (extract(epoch from twchild.tfrom)+twchild.seconds)<=(extract(epoch from twparent.tfrom)+twparent.seconds)
              AND twchild.seconds<twparent.seconds
              ORDER BY seconds
              LIMIT 1 )
            WHERE twchild.id IN (?) AND twchild.processed>0 AND twchild.found>0
            ",$wids);    
        Monda::mcommit();
    }
    
    function twDelete($opts) {
        Monda::mbegin();
        $wids=self::twToIds($opts);
        CliDebug::warn(sprintf("Deleting timewindows for zabbix_id %d from %s to %s, length %s (%d windows)\n",
                    $opts->zid,
                    date("Y-m-d H:i",$opts->start),
                    date("Y-m-d H:i",$opts->end),
                    join(",",$opts->length),
                    count($wids)));
        if (count($wids)>0) {
            $d1=Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)",$wids);
            $d2=Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)",$wids);
            $d3=Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d4=Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d5=Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d6=Monda::mquery("DELETE FROM timewindow WHERE id IN (?)",$wids);
        }
        return(Monda::mcommit());
    }
    
    function twEmpty($opts) {
        Monda::mbegin();
        $wids=self::twToIds($opts);
        CliDebug::warn(sprintf("Emptying timewindows for zabbix_id %d from %s to %s, length %s (%d windows)\n",
                    $opts->zid,
                    date("Y-m-d H:i",$opts->start),
                    date("Y-m-d H:i",$opts->end),
                    join(",",$opts->length),
                    count($wids)));
        if (count($wids)>0) {
            $d1=Monda::mquery("DELETE FROM itemstat WHERE windowid IN (?)",$wids);
            $d2=Monda::mquery("DELETE FROM hoststat WHERE windowid IN (?)",$wids);
            $d3=Monda::mquery("DELETE FROM itemcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d4=Monda::mquery("DELETE FROM hostcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d5=Monda::mquery("DELETE FROM windowcorr WHERE windowid1 IN (?) OR windowid2 IN (?)",$wids,$wids);
            $d6=Monda::mquery("UPDATE timewindow SET updated=?, processed=0,found=0,loi=0 WHERE id IN (?)",
                    New DateTime(),$wids);
        }
        return(Monda::mcommit());
    }
    
}

?>
