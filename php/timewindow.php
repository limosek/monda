#!/usr/bin/php
<?php
//error_reporting(E_ERROR | E_PARSE);
define("GH_VERSION", "3");
define("MIN_ITEMS",30);
define("TWPRECISION",300);

require dirname(__FILE__).'/common.inc.php';

init_api(false);


$opts = getopt(
        "F:f:T:t:I:d:welL"
);

if (!$opts) {
    fprintf(STDERR, "
  timewindow 
   -F timespec          Specify start_time relative to end_time
   -f timespec          Specify start_time relative to now or absolute
   -T timespec          Specify end_time relative to start_time
   -t timespec          Specify end_time relative to now or absolute		example -1day
   -d description       Time window description
   -I windowid          Window id
   -w                   Overwrite data in db if window already exists
   -L                   Only update loi (no refresh of zabbix data)
   -e			Write stderror messages
   
   timespec examples:   -1day, 3day, -8hour, '2013-01-01 00:00', @1379323692
  \n\n");
    errorexit("", 1);
}


if (isset($opts["f"])) {
    $tfrom = timetoseconds($opts["f"]);
} else {
    if (isset($opts["F"])) {
        $tfrom = timetoseconds($opts["F"]) - time() + $to_time;
    }
}

if (isset($opts["t"])) {
    $tto = timetoseconds($opts["t"]);
} else {
    if (isset($opts["T"])) {
        $tto = timetoseconds($opts["T"]) - time() + $tfrom;
    }
}

if (isset($opts["d"])) {
    $description=$opts["d"];
} else {
    $description='';
}

if (isset($opts["w"])) {
    $overwrite=true;
} else {
    $overwrite=false;
}

if (isset($opts["I"])) {
    $windowid=$opts["I"];
}

if (isset($opts["e"])) {
    $stderr = true;
} else {
    $stderr = false;
}

$loiupdate = true;

if (isset($opts["L"])) {
    $onlyloi = true;
} else {
    $onlyloi = false;
}

if ((!$tto || !$tfrom) && !$windowid) {
    echo "Missing from and to time!";
    exit(2);
}

function cfloat($f,$n1=16,$n2=4) {
    $n3=$n1-$n2;
    return(sprintf("%e",$f));
}

function TimeWindowPgsql($tfrom, $tto, $description) {
    global $mq, $zq, $overwrite,$loiupdate,$onlyloi;

    $pgt1 = pgsqlQuery($mq,"BEGIN");
    $pqz1 = pgsqlQuery($zq, "SELECT set_backend_priority(pg_backend_pid(), 19);");
    $pqm1 = pgsqlQuery($mq, "SELECT set_backend_priority(pg_backend_pid(), 19);");

    $tfrom=(round($tfrom/TWPRECISION)*TWPRECISION);
    $tto=(round($tto/TWPRECISION)*TWPRECISION);
    
    $pqrm1 = pgsqlQuery($mq, "SELECT id AS wid FROM timewindow WHERE tfrom=to_timestamp($tfrom) AND tto=to_timestamp($tto)");
    if (pg_num_rows($pqrm1) == 1) {
        $wid = pg_fetch_object($pqrm1);
        $wid = $wid->wid;
        $found=true;
    } else {
        $pq2 = pgsqlQuery($mq, sprintf(
                        "INSERT INTO timewindow(
            description, tfrom, tto)
         VALUES ('%s', to_timestamp('%s'), to_timestamp('%s'));
         SELECT currval('timewindowid') AS wid;", $description, $tfrom, $tto));
        $wid = pg_fetch_object($pq2);
        $wid = $wid->wid;
        $found=false;
    }
    if ($overwrite && $found) {
        $pqrm2 = pgsqlQuery($mq, "DELETE FROM itemstat WHERE windowid=$wid");
        if ($description) {
            $pqup = pgsqlQuery($mq, "UPDATE timewindow SET description='$description' WHERE id=$wid");
        }
    } elseif ($found) {
        if ($onlyloi) {
            return($wid);
        } else {
            echo "Window already exists and no update wanted. See -w option.\n";
            exit(2);
        }
    }
    
    $pq3 = pgsqlQuery($zq, sprintf(
                    "SELECT itemid,min(value) AS min,max(value) AS max,avg(value) AS avg,stddev(value) AS stddev,
                    max(value)-min(value) AS delta, count(*) AS cnt
                    FROM history
                    WHERE clock>%s and clock<%s
                    GROUP BY itemid
                    UNION
                    SELECT itemid,min(value) AS min,max(value) AS max,avg(value) AS avg,stddev(value) AS stddev,
                    max(value)-min(value) AS delta, count(*) AS cnt
                    FROM history_uint
                    WHERE clock>%s and clock<%s
                    GROUP BY itemid
                    ", $tfrom, $tto,$tfrom,$tto
            ));

    $ignored=0;
    $processed=0;
    while ($s = pg_fetch_object($pq3)) {
        if ($s->delta == 0 || $s->stddev == 0 || $s->cnt < MIN_ITEMS || abs($s->avg)<0.1) {
            $ignored++;
            continue;
        }
        $processed++;
        if ($s->avg<>0) {
            $cv=$s->stddev/$s->avg;
        } else {
            $cv=false;
        }
        $loi=round(abs($cv));
        $pq4 = pgsqlQuery($mq, sprintf(
                "INSERT INTO itemstat(
                cnt, itemid, windowid,   avg_,   min_,   max_,    stddev_,       cv,     loi)
        VALUES (%d, %d,     %d,         %s      ,       %s,             %s,             %s ,   %s,     %d);",
            $s->cnt, $s->itemid, $wid, cfloat($s->avg), cfloat($s->min), cfloat($s->max), cfloat($s->stddev), cfloat($cv),      $loi
                ));
    }
    $pgt2 = pgsqlQuery($mq,"COMMIT");
    echo "Ignored $ignored items, processed $processed items.\n";
    return($wid);
}

function computeTimewindow($tfrom,$tto,$description=false) {
    if (ZABBIX_DB_TYPE == "MYSQL") {
        return(TimeWindowMysql($tfrom,$tto,$description));
    } elseif (ZABBIX_DB_TYPE == "POSTGRESQL") {
        return(TimeWindowPgsql($tfrom,$tto,$description));
    } else {
        errorexit("Unknown Zabbix database type " . ZABBIX_DB_TYPE, 13);
    }
}

function TimeWindowLoi($wid) {
    if (ZABBIX_DB_TYPE == "MYSQL") {
        return(TimeWindowLoiMysql($wid));
    } elseif (ZABBIX_DB_TYPE == "POSTGRESQL") {
        return(TimeWindowLoiPgsql($wid));
    } else {
        errorexit("Unknown Zabbix database type " . ZABBIX_DB_TYPE, 13);
    }
}

function TimeWindowLoiPgsql($wid)  {
    global $mq;
    $pq1 = pgsqlQuery($mq,"
        UPDATE timewindow 
        SET loi=(SELECT avg(loi) FROM itemstat where windowid=$wid)
        WHERE id=$wid;
        COMMIT;
        ");
}

if ($windowid) {
    $overwrite=true;
    $pqwi1 = pgsqlQuery($mq, "SELECT extract(epoch from tfrom) AS tfrom,extract(epoch from tto) AS tto
        FROM timewindow WHERE id=$windowid");
    $row=pg_fetch_object($pqwi1);
    $tfrom=$row->tfrom;
    $tto=$row->tto;
}

$wid=computeTimewindow($tfrom, $tto,$description);
if ($wid && ($loiupdate||$onlyloi)) {
    TimeWindowLoi($wid);
}

?>
