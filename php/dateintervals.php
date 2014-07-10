#!/usr/bin/php
<?php
error_reporting(E_ERROR);

require dirname(__FILE__).'/common.inc.php';

$opts = getopt(
        "F:T:S:t:D:s:w"
);

if (!$opts) {
    fprintf(STDERR, "
  gethistory 
   -F timespec          Specify start_time 
   -T timespec          Specify end_time 
   -S 			Specify time step (1hour)
   -D datespec          Specify output date format (Y-m-d h:i)
   -t                   Roud time to this seconds
   -w                   Output window times (start,stop)
   
   timespec examples:   -1day, 3day, -8hour, '2013-01-01 00:00', @1379323692
  \n\n");
    errorexit("", 1);
}

$from = timetoseconds($opts['F']);
$to = timetoseconds($opts['T']);
if (!$opts['S']) {
    $step = 3600;
} else {
    $step = timetoseconds($opts['S']) - time();
}

if ($opts['D']) {
    $format = $opts['D'];
} else {
    $format = "Y-m-d H:i";
}

if (isset($opts['t'])) {
    $from = round($from / $opts['t']) * $opts['t'];
}

if (isset($opts['w'])) {
    $windowtime=true;
} else {
    $windowtime=false;
}

$tme = $from;
while ($tme <= ($to - $step)) {
    if ($windowtime) {
        echo date($format, $tme)." ".date($format, $tme+$step) . "\n";
    } else {
        echo date($format, $tme) . "\n";
    }
    $tme+=$step;
}
