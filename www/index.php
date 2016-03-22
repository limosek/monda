<?php

if (getenv("MONDA_TMP")) {
    $tmpdir=getenv("MONDA_TMP");
} else {
    $tmpdir=__DIR__ . "/../temp";
}
$cachedir="$tmpdir/cache";
$sqlcachedir="$cachedir/sql";
$apicachedir="$cachedir/api";

if (getenv("MONDA_LOGDIR")) {
    $logdir=getenv("MONDA_LOGDIR");
} else {
    $logdir=__DIR__ . "/../log";
}

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
putenv("MONDA_LOGDIR=$logdir");

putenv("MONDA_WWW=1");
if (!getenv("MONDARC")) {
    putenv("MONDARC=" . __DIR__ . "/../app/config/mondarc");
}

$container = require __DIR__ . '/../app/bootstrap.php';

$container->getByType('Nette\Application\Application')->run();
