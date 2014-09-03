<?php

if (getenv("MONDA_TMP")) {
    $tmpdir=getenv("MONDA_TMP");
} else {
    $tmpdir=__DIR__ . "/../temp/web";
}

if (getenv("MONDA_LOG")) {
    $logdir=getenv("MONDA_LOG");
} else {
    $logdir=__DIR__ . "/../log/web";
}

$cachedir="$tmpdir/cache";
$sqlcachedir="$cachedir/sql";
$apicachedir="$cachedir/api";

if (!getenv("MONDA_TMP")) {
    putenv("MONDA_TMP=$tmpdir");
}

if (!getenv("MONDA_LOG")) {
    putenv("MONDA_LOG=$logdir");
}

if (getenv("MONDARC")) {
    $cfgf=getenv("MONDARC");
} else {
    $cfgf=__DIR__."/../app/config/monda.rc";
}

putenv("MONDARC=$cfgf");
putenv("MONDA_CACHEDIR=$cachedir");
putenv("MONDA_SQLCACHEDIR=$sqlcachedir");
putenv("MONDA_APICACHEDIR=$apicachedir");

if (!file_exists($sqlcachedir)) {
    mkdir($sqlcachedir,0700,true);
}
if (!file_exists($apicachedir)) {
    mkdir($apicachedir,0700,true);
}

$container = require __DIR__ . '/../app/bootstrap.php';

Nette\Diagnostics\Debugger::$strictMode = false;

$container->getService('application')->run();
