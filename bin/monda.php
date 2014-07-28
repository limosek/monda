#!/usr/bin/php
<?php

use Nette\Utils\Strings,
    Nette\Security\Passwords,
    Nette\Diagnostics\Debugger,
    Nette\Database\Context,
    \ZabbixApi;

proc_nice(19);

if (!file_exists(__DIR__ . "/../temp/cache/sql")) {
    mkdir(__DIR__ . "/../temp/cache/sql",0700,true);
}
if (!file_exists(__DIR__ . "/../temp/cache/api")) {
    mkdir(__DIR__ . "/../temp/cache/api",0700,true);
}

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
    $cfgargs=strtr(file_get_contents($cfgf),"\n"," ");
    $cmdargs=$_SERVER["argv"];
    putenv("MONDA_PASS2=true");
    $cmd=sprintf("%s %s %s --foo %s",array_shift($cmdargs),array_shift($cmdargs),$cfgargs,join(" ",$cmdargs));
    //echo "$cmd\n";exit;
    system($cmd,$ret);
    exit($ret);
}
$dbgidx=array_search("-D",$_SERVER["argv"]);
if ($dbgidx && array_key_exists($dbgidx, $_SERVER["argv"])) {
        putenv("MONDA_DEBUG=".$_SERVER["argv"][$dbgidx+1]);
}

$container->application->run();
