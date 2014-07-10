#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_PARSE);

require dirname(__FILE__) . '/common.inc.php';
define("MIN_CORR", 30);
define("TIME_PRECISION",15);

$api=init_api();

$opts = getopt(
        "w:W:i:I:euhr"
);

if (isset($opts["e"])) {
    $stderr = true;
} else {
    $stderr = false;
}
if (isset($opts["r"])) {
    $random=true;
} else {
    $random=false;
}

if ($opts["w"]) {
    $wi1 = windowrow($opts["w"]);
} else {
    $wi1 = windowrow("@+3600");
}
if ($opts["W"]) {
    $wi2 = windowrow($opts["W"]);
} else {
    $wi2 = windowrow("@+3600");
}
if ($opts["i"]) {
    $itemid1 = $opts["i"];
}
if ($opts["I"]) {
    $itemid2 = $opts["I"];
}
if (isset($opts["u"])) {
    $update=true;
} else {
    $udpate=false;
}

if (isset($opts["h"])) {
    fprintf(STDERR, "
  corr 
   -w windowid          Specify window1[/length] (see example)
   -W windowid          Specify window2
   -i itemid
   -I itemid
   -u                   Update even if itemcorr exists
   -r                   Random window select
   -e			Write stderror messages
   
Example window specifications:
123 = windowid
@1394492400 = unix_timestamp exactly
@<1394492400 = --//-- less than
@<1394492400,1394492400> = --//-- between

  \n\n");
    errorexit("", 1);
}

function windowrow($wid) {
    global $mq,$random;
    if (is_numeric($wid)) {
        $pq1 = pgsqlQuery($mq, "SELECT id,loi,extract(epoch from tfrom) AS tfrom,extract(epoch from tto) AS tto,description FROM timewindow WHERE id=$wid");
        return($pq1);
    }
    List($time,$length)=preg_split("#/#",$wid);
    if (preg_match('/^@(\d*)$/',$time,$r)) {
        $usql=sprintf("extract(epoch from tfrom)=%d AND",$r[1]);
    } elseif (preg_match("/^@\>(\d*)\$/",$time,$r)) {
        $usql=sprintf("extract(epoch from tfrom)>%d AND",$r[1]);
    } elseif (preg_match("/^@\<(\d*)\$/",$time,$r)) {
        $usql=sprintf("extract(epoch from tfrom)<%d AND",$r[1]);
    } elseif (preg_match("/^@\<(\d*),(\d*)>\$/",$time,$r)) {
        $usql=sprintf("extract(epoch from tfrom)>%d AND extract(epoch from tfrom)<%d AND",$r[1],$r[2]);
    }
    if ($length) {
        $lsql="extract(epoch from (tto-tfrom))=$length AND";
    }
    if ($random) {
        $order="r";
    } else {
        $order="loi DESC,tfrom";
    }
    $pq1 = pgsqlQuery($mq, "SELECT random() AS r,id,loi,extract(epoch from tfrom) AS tfrom,extract(epoch from tto) AS tto,tto-tfrom AS len,description FROM timewindow WHERE $usql $lsql true ORDER BY $order"); 
    return($pq1);
}

function Correlate($w1, $w2) {
    global $mq, $zq, $update;

    echo windowinfo($w1->id)." ".windowinfo($w2->id)."\n";
    
    if (($w2->tto) - ($w2->tfrom) <> ($w1->tto) - ($w1->tfrom)) {
        echo "Windows are not same length!\n";
        return(false);
    }
    $pqc = pgsqlQuery($mq, "BEGIN;");
    $pqwc1 = pgsqlQuery($mq, "SELECT windowid1,windowid2
            FROM windowcorr
            WHERE windowid1=$w1->id AND windowid2=$w2->id");
    if (!$update && pg_num_rows($pqwc1)>0) {
        echo "Correlation already in DB\n";
        return(false);
    }
    
    if ($w1->id == $w2->id) {
        $sameitem = "AND is1.itemid<>is2.itemid";
    } else {
        $sameitem = "AND is1.itemid=is2.itemid";
    }
    $pqi1 = pgsqlQuery($mq, "SELECT DISTINCT is1.itemid AS itemid1, is1.loi, is2.itemid AS itemid2, is2.loi, is1.loi+is2.loi AS oloi
            FROM itemstat is1
            JOIN itemstat is2 ON (is2.windowid=$w2->id)
            WHERE
                is1.windowid=$w1->id AND is2.windowid=$w2->id 
                $sameitem
            ORDER by oloi DESC
            LIMIT 100");
    if (pg_num_rows($pqi1)==0) {
        //printf(__DIR__.sprintf("/timewindow.php -f @%d -t @%d -e\n",$w1->tfrom,$w1->tto));
        //printf(__DIR__.sprintf("/timewindow.php -f @%d -t @%d -e\n",$w2->tfrom,$w2->tto));
        return(false);
    }
    $rows1 = pg_fetch_all($pqi1);
    $itemids1 = array();
    $itemids2 = array();
    foreach ($rows1 as $iarr) {
        $itemids1[] = $iarr["itemid1"];
        $itemids2[] = $iarr["itemid2"];
    }
    item2host($itemids1);
    item2host($itemids2);
    $itemid1 = join(",", $itemids1);
    $itemid2 = join(",", $itemids2);
    if ($w1->id == $w2->id) {
        $sameitem = "AND h1.itemid<>h2.itemid";
        $tdiff=0;
    } else {
        $sameitem = "AND h1.itemid=h2.itemid";
        $tdiff=$w2->tfrom-$w1->tfrom;
    }
    $pq3 = pgsqlQuery($zq, sprintf(
                    "SELECT h1.itemid AS itemid1, h2.itemid AS itemid2, COUNT(*) AS cnt,CORR(h1.value,h2.value) AS corr
                        FROM history h1
                JOIN history h2 ON (ABS(h1.clock-h2.clock+(%d))<%d AND h2.itemid IN (%s))
                WHERE h1.itemid IN (%s)
                 AND h1.clock>%d AND h1.clock<%d
                 AND h2.clock>%d AND h2.clock<%d
                 $sameitem
                 GROUP BY h1.itemid,h2.itemid
                ",$tdiff,TIME_PRECISION,$itemid2, $itemid1, $w1->tfrom, $w1->tto, $w2->tfrom, $w2->tto, $itemid2
            ));
    $nulls=0;
    $avgloi=0;
    $allcorrs=0;
    $avgcnt=0;
    $hostcorr=Array();
    $hostcorrcnt=Array();
    while ($corr = pg_fetch_object($pq3)) {
        if ($update) {
            $pqrm1=pgsqlQuery($mq,sprintf("DELETE FROM itemcorr
                WHERE windowid1=%d AND windowid2=%d AND itemid1=%d AND itemid2=%d;",
                      $w1->id,            $w2->id,          $corr->itemid1, $corr->itemid2
                    ));
            $pqrm2=pgsqlQuery($mq,sprintf("DELETE FROM windowcorr
                WHERE windowid1=%d AND windowid2=%d;",
                      $w1->id,            $w2->id
                    ));
            $pqrm3=pgsqlQuery($mq,sprintf("DELETE FROM hostcorr
                WHERE windowid1=%d AND windowid2=%d;",
                      $w1->id,            $w2->id
                    ));
        }
        $allcorrs++;
        if ($corr->cnt > MIN_CORR && $corr->corr) {
            $loi = abs(round($corr->corr * 100));
            $avgloi+=$loi;
            $avgcnt++;
        } else {
            $nulls++;
            $corr->corr=0; $loi=0;
        }
        $pq4 = pgsqlQuery($mq, sprintf("INSERT INTO itemcorr(
            windowid1, windowid2, itemid1, itemid2, corr, cnt, loi)
            VALUES (
            %d,     %d,             %d,             %d,         %f,     %d,         %s)",
           $w1->id, $w2->id, $corr->itemid1, $corr->itemid2, $corr->corr, $corr->cnt, $loi));
        if (!$pq4) {
            echo "Error inserting row! Exiting.\n";
            exit(2);
        }
        $hostid1=item2host($corr->itemid1);
        $hostid2=item2host($corr->itemid2);
        if ($hostid1 && $hostid2) {
            $hostcorr[$hostid1][$hostid2]+=$corr->corr;
            $hostcorrcnt[$hostid1][$hostid2]+=1;
        }
    }
    if ($avgcnt>0) {
        $avgloi=($avgloi/$avgcnt)*($w1->tto - $w1->tfrom)/3600;
        foreach ($hostcorr as $hostid1=>$host1) {
            foreach ($host1 as $hostid2=>$corr) {
                $corr=$corr/$hostcorrcnt[$hostid1][$hostid2];
                $loi=$corr*100;
                $pqh1 = pgsqlQuery($mq, "INSERT INTO hostcorr (windowid1,windowid2,hostid1, hostid2, corr, loi)
        VALUES ( $w1->id,$w2->id, $hostid1,$hostid2, $corr, $loi);");
            }
        }
    } else {
        $avgloi=0;
    }
    $pqw1 = pgsqlQuery($mq, "INSERT INTO windowcorr (windowid1,windowid2,loi)
        VALUES ( $w1->id,$w2->id, $avgloi);");
    $pqc = pgsqlQuery($mq, "COMMIT;");
    echo "Selected $allcorrs rows, ignored $nulls corelations, average LOI is $avgloi\n";
}

$pqz1 = pgsqlQuery($zq, "SELECT set_backend_priority(pg_backend_pid(), 19);");
$pqm1 = pgsqlQuery($mq, "SELECT set_backend_priority(pg_backend_pid(), 19);");

while ($w1=pg_fetch_object($wi1)) {
    $w1ids[]=$w1;
}
while ($w2=pg_fetch_object($wi2)) {
    $w2ids[]=$w2;
}
foreach ($w1ids as $id1) {
    foreach ($w2ids as $id2) {
        Correlate($id1, $id2);
    }
}

