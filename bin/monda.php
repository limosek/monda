#!/usr/bin/php
<?php

proc_nice(19);
if (getenv("MONDA_TMP")) {
    $tmpdir=getenv("MONDA_TMP");
} else {
    $tmpdir=__DIR__ . "/../temp";
}
$cachedir="$tmpdir/cache";
$sqlcachedir="$cachedir/sql";
$apicachedir="$cachedir/api";

if (!file_exists($sqlcachedir)) {
    mkdir($sqlcachedir,0700,true);
}
if (!file_exists($apicachedir)) {
    mkdir($apicachedir,0700,true);
}

putenv("MONDA_CACHEDIR=$cachedir");
putenv("MONDA_SQLCACHEDIR=$sqlcachedir");
putenv("MONDA_APICACHEDIR=$apicachedir");
putenv("MONDA_TMP=$tmpdir");
putenv("MONDA_CLI=yes");
putenv("MONDA_LOG=" . __DIR__ . "/../log");

if (getenv("MONDARC")) {
    $cfgf=getenv("MONDARC");
} else {
    $cfgf=getenv("HOME")."/.mondarc";
}
putenv("MONDARC=$cfgf");

$container = require __DIR__ . '/../app/bootstrap.php';

use Tracy\Debugger;
Debugger::$maxDepth = 15;
Debugger::$maxLen = 2000;

$container->application->run();
