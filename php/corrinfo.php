#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_PARSE);

require dirname(__FILE__).'/common.inc.php';

$api=init_api();


$opts = getopt(
        "F:T:f:t:enhHso"
);

if (isset($opts["h"])) {
    fprintf(STDERR, "
  timewindow 
   -F timespec          Specify start_time relative to end_time
   -f timespec          Specify start_time relative to now or absolute
   -T timespec          Specify end_time relative to start_time
   -t timespec          Specify end_time relative to now or absolute		example -1day
   -s                   Skip same items (only cross-item correlations)
   -o                   Skip items within one host
   -H                   Output history for items
   -e			Write stderror messages
   -n                   Do not resolve hostnames and items
   
   timespec examples:   -1day, 3day, -8hour, '2013-01-01 00:00', @1379323692
  \n\n");
    errorexit("", 1);
}

if (isset($opts["e"])) {
    $stderr = true;
} else {
    $stderr = false;
}

if (isset($opts["n"])) {
    $numeric=true;
} else {
    $numeric=false;
}

if (isset($opts["s"])) {
    $cross=true;
} else {
    $cross=false;
}

if (isset($opts["o"])) {
    $ocross=true;
    $cross=true;
} else {
    $ocross=false;
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

if (isset($opts["H"])) {
    $history = true;
}

if ($tto) {
    $ttosql="extract(epoch from tw1.tto)<$tto AND extract(epoch from tw2.tto)<$tto AND ";
}
if ($tfrom) {
    $tfromsql="extract(epoch from tw1.tfrom)>$tfrom AND extract(epoch from tw2.tfrom)>$tfrom AND";
}

if ($cross) {
    $crosssql="itemcorr.itemid1<>itemcorr.itemid2 AND";
}

$psql1=pgsqlQuery($mq, "SELECT itemcorr.loi AS loi,itemcorr.cnt AS cnt,itemcorr.corr AS corr,
    tw1.loi AS w1loi, tw2.loi AS w2loi,
    itemcorr.itemid1 AS itemid1, itemcorr.itemid2 AS itemid2,
    itemcorr.windowid1 AS windowid1, itemcorr.windowid2 AS windowid2,
    extract(epoch from tw1.tfrom) AS tw1from,extract(epoch from tw1.tto) AS tw1to,
                        extract(epoch from tw2.tfrom) AS tw2from,extract(epoch from tw2.tto) AS tw2to
            FROM itemcorr
            JOIN timewindow tw1 ON (windowid1=tw1.id)
            JOIN timewindow tw2 ON (windowid2=tw2.id)
            WHERE corr>0.5
                AND ((windowid1=windowid2 AND itemid1<>itemid2) OR (windowid1<>windowid2)) AND
                $ttosql
                $tfromsql
                $crosssql
                true
            ORDER BY tw1.loi DESC, tw2.loi DESC, itemcorr.loi DESC");

while ($row=pg_fetch_object($psql1)) {
    item2host(Array($row->itemid1,$row->itemid2));
    $host1=item2host($row->itemid1);
    $host2=item2host($row->itemid2);
    //echo $host1." ".$host2."\n";
    if ($ocross && $host1==$host2) {
        continue;
    }
    echo "Window ".windowinfo($row->windowid1,$numeric)." ==>  ".iteminfo($row->itemid1,$numeric)."\n";
    echo "Window ".windowinfo($row->windowid2,$numeric)." ==> ".iteminfo($row->itemid2,$numeric)."\n";
    echo "Corr=$row->corr,cnt=$row->cnt,loi=$row->loi,windowloi=($row->w1loi,$row->w2loi)\n\n";
    if ($history) {
        itemdata2csv($row->itemid1,$row->tw1from,$row->tw1to);
    }
}

?>