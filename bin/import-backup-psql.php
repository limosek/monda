#!/usr/bin/php
<?php

require(__DIR__."/../app/model/Import.php");
//require(__DIR__."/../app/model/CliDebug.php");

$tables=Array();
$columns=Array();
array_shift($argv);
foreach ($argv as $arg) {
    if (preg_match("/:/",$arg)) {
        List($intable,$outtable)=preg_split("/:/",$arg);
        $tables[$intable]=$outtable;
    }
    if (preg_match("#/#",$arg)) {
        List($intable,$column,$cond,$val)=preg_split("#/#",$arg);
        $columns[$intable][$column][$cond]=$val;
    }
}
if (count($tables)<1) {
    echo "\nImport data from postgresql backup to zabbix.\n"
    . "Reads posgtres dump file on input, outputs sql commands in output.\n"
    . "Table specifications: intable:otable\n"
    . "Column specifiations: intable/colindex/min/100 intable/colindex/max/100\n"
    . "$0 <table specifiations> <column specifications>\n\n"
    . "example: $0 history:history_tmp history/1/min/1000000 \n"
    . "will import from backup 'history' table into 'history_tmp' and second column (clock) has to be bigget than 1000000.\n\n";
    exit(1);
}

App\Model\Import::readPgBackup("php://stdin",true,$tables,$columns);
