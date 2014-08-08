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

define("TCHARS"," \t\n\r\0\x0B'\"");

if (getenv("MONDARC")) {
    $cfgf=getenv("MONDARC");
} else {
    $cfgf=getenv("HOME")."/.mondarc";
}
if (file_exists($cfgf) && !getenv("MONDA_PASS2")) {
    $cfgargs="";
    foreach (file($cfgf) as $line) {
        if (preg_match("#^-#",$line)) {
            if (preg_match("#^(-[a-zA-Z0-9_\-]*) {1,4}(.*)$#",$line,$regs)) {
                $option=trim($regs[1],TCHARS);
                $value=trim($regs[2],TCHARS);
                $cfgargs .= " '$option' '$value' ";
            } else {
                $cfgargs.=" '".trim($line,TCHARS)."' ";
            }
        }
    }
    $cmdargs=$_SERVER["argv"];
    foreach ($cmdargs as $id=>$cmd) {
        $cmd=trim($cmd,TCHARS);
        $cmdargs[$id]="'$cmd'";
    }
    putenv("MONDA_PASS2=true");
    $cmd=trim(array_shift($cmdargs),TCHARS);
    $presenter=trim(array_shift($cmdargs),TCHARS);
    if (!$presenter) {
        $presenter="default";
    }
    $cmd=sprintf("'%s' '%s' %s --foo %s",$cmd,$presenter,$cfgargs,join(" ",$cmdargs));
    //echo $cmd;exit;
    system($cmd,$ret);
    exit($ret);
}
$dbgidx=array_search("-D",$_SERVER["argv"]);
if ($dbgidx && array_key_exists($dbgidx, $_SERVER["argv"])) {
        putenv("MONDA_DEBUG=".$_SERVER["argv"][$dbgidx+1]);
}

$container->application->run();
