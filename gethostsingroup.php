#!/usr/bin/php
<?php
error_reporting(E_ERROR);

// load ZabbixApi
require 'PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require 'PhpZabbixApi_Library/ZabbixApi.class.php';
require './common.inc.php';
if (!isset($argv[1])) {
  die($argv[0]." hostgroup\n");
}

try {
	$api=init_api();
	
	$hg=$api->hostGroupGet(
			  Array(
			  "filter" => Array("name" => array($argv[1])),
			  "output" => "extend"
			  )
			);
	if (!isset($hg[0])) {
	  fprintf(STDERR,"Group does not exists!\n");
	  exit(1);
	}
	$gid=$hg[0]->groupid;
	$h=$api->hostGet(
			  Array(
			  "groupids" => Array($gid),
			  "output" => "extend"
			  )
			);
	foreach ($h as $host) {
	  echo $host->name."\n";
	}

} catch(Exception $e) {
	echo $e->getMessage();
}

