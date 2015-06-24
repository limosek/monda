<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;
$configurator->setTempDirectory(getenv("MONDA_TMP"));


$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->addDirectory(__DIR__ . '/../vendor/others')
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

$container = $configurator->createContainer();

use Tracy\Debugger;

if (!getenv("MONDA_CLI")) {
    Debugger::enable(Array('127.0.0.1'),__DIR__."/../log/");
} else {
    Debugger::enable(Debugger::DETECT,__DIR__."/../log/");
    Debugger::$productionMode=false;
    Debugger::setLogger(New \App\Model\CliLogger());
}

return $container;
