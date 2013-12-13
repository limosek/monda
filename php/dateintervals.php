#!/usr/bin/php
<?php
//error_reporting(E_ERROR);

require dirname(__FILE__).'/common.inc.php';

$opts = getopt(
        "F:T:S:t:D:s:"
);

$F = $opts['F'];
$from = timetoseconds($F);
$T = $opts['T'];
$to = timetoseconds($T);
$S = $opts['S'];
$step = timetoseconds($S) - time();
if ($opts['D']) {
    $format = $opts['D'];
} else {
    $format = "Y-m-d H:i";
}

if (isset($opts['t'])) {
    $from = round($from / $opts['t']) * $opts['t'];
}

$tme = $from;
while ($tme <= ($to - $step)) {
    echo date($format, $tme) . "\n";
    $tme+=$step;
}
