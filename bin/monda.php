#!/usr/bin/php
<?php

use Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

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

$container = require __DIR__ . '/../app/bootstrap.php';
Debugger::$maxDepth = 15;
Debugger::$maxLen = 2000;
Debugger::$browser="chromium-browser";

if (getenv("MONDARC")) {
    $cfgf=getenv("MONDARC");
} else {
    $cfgf=getenv("HOME")."/.mondarc";
}
if (file_exists($cfgf) && !getenv("MONDA_PASS2")) {
    $cfgargs="";
    foreach (file($cfgf) as $line) {
        if (preg_match("#^-#",$line)) { 
            $cfgargs.=strtr($line,"\n"," ");
        }
    }
    $cmdargs=$_SERVER["argv"];
    foreach ($cmdargs as $id=>$cmd) {
        $cmdargs[$id]="'$cmd'";
    }
    putenv("MONDA_PASS2=true");
    $cmd=array_shift($cmdargs);
    $presenter=array_shift($cmdargs);
    if (!$presenter) {
        $presenter="default";
    }
    $cmd=sprintf("'%s' '%s' %s --foo %s",$cmd,$presenter,$cfgargs,join(" ",$cmdargs));
    //echo "$cmd\n";exit;
    system($cmd,$ret);
    exit($ret);
}
$dbgidx=array_search("-D",$_SERVER["argv"]);
if ($dbgidx && array_key_exists($dbgidx, $_SERVER["argv"])) {
        putenv("MONDA_DEBUG=".$_SERVER["argv"][$dbgidx+1]);
}

$container->application->run();
