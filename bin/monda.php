#!/usr/bin/php
<?php

$container = require __DIR__ . '/../app/bootstrap.php';

use Tracy\Debugger;
Debugger::$maxDepth = 15;
Debugger::$maxLen = 2000;

$container->application->run();
