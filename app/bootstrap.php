<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;
$configurator->setTempDirectory(getenv("MONDA_TMP"));

if (!getenv("MONDA_CLI")) {
    $configurator->setDebugMode(Array('127.0.0.1'));
    if (getenv("MONDA_LOG")) {
        $configurator->enableDebugger(getenv("MONDA_LOG"));
    }
} else {
    $configurator->setDebugMode(true); 
}

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->addDirectory(__DIR__ . '/../vendor/others')
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

$container = $configurator->createContainer();

define("TCHARS"," \t\n\r\0\x0B'\"");
define("WCHARS"," -\t\n\r\0\x0B'\"");

if (file_exists(getenv("MONDARC")) && !getenv("MONDA_PASS2")) {
    $cfgargs="";
    $cfggetargs=Array();
    foreach (file(getenv("MONDARC")) as $line) {
        if (preg_match("#^-#",$line)) {
            if (preg_match("#^(-[a-zA-Z0-9_\-]*) {1,4}(.*)$#",$line,$regs)) {
                $option=trim($regs[1],TCHARS);
                $woption=trim($regs[1],WCHARS);
                $value=trim($regs[2],TCHARS);
                $cfgargs .= " '$option' '$value' ";
                $cfggetargs[$woption]=$value;
            } else {
                $cfgargs.=" '".trim($line,TCHARS)."' ";
                $cfggetargs[trim($line,WCHARS)]=true;
            }
        }
    }
    if (getenv("MONDA_CLI")) {
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
        if (preg_match("/:/",$presenter)) {
            List($p,$a)=preg_split("/:/",$presenter);
        } else {
            $p=$presenter;
            $a="Default";
        }
        if ($p=="hm") $p="HtmlMap";
        if ($p=="gm") $p="GraphvizMap";
        $presenter="$p:$a";
        $cmd=sprintf("'%s' '%s' %s --foo %s",$cmd,$presenter,$cfgargs,join(" ",$cmdargs));
        system($cmd,$ret);
        exit($ret);
    } else {
        foreach ($cfggetargs as $var=>$value) {
            if (!array_key_exists($var,$_REQUEST)) {
                $_GET[$var]=$value;
            }
        }
    }
}

return $container;
