#!/usr/bin/php
<?
error_reporting(E_ERROR);

// load ZabbixApi
require 'PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require 'PhpZabbixApi_Library/ZabbixApi.class.php';
require './common.inc.php';

$hist=time()-3600;
$rhost='';
$ritem='';
$nritem='Someabnormalkeywhichdoesnotexists';
$time_step=60;
$histapi=true;
$backuptable=false;

$opts=getopt(
	"h:t:H:G:a:i:I:SB"
);

if (isset($opts["t"])) {
	$to_time=timetoseconds($opts["t"]);
} else {
	$to_time=time();
}

if (isset($opts["h"])) {
	$hist=timetoseconds($opts["h"]);
}

if (isset($opts["S"])) {
	$histapi=false;
}

if (isset($opts["B"])) {
	$backuptable=true;
	$histapi=false;
}

if (isset($opts["H"])) {
	$rhost=$opts["H"];
}

if (isset($opts["G"])) {
	$rgroup=$opts["G"];
}

if (isset($opts["a"])) {
	$time_step=$opts["a"];
}

if (isset($opts["i"])) {
	$ritem=$opts["i"];
}

if (isset($opts["I"])) {
	$nritem=$opts["I"];
}

try {
	$api=init_api();
	
	if ($rhost=="" && $rgroup=="" && $ritem=="") {
	  fprintf(STDERR,"#Fetching all hosts and items!");
	}
	$sq=Array(
	  "output" => 'extend'
	);
	if ($rhost) $sq["host"]=$rhost;
	if ($rhost) $sq["group"]=$rgroup;
	$items = $api->itemGet($sq);
		
	$itemids=Array();
	$history=array();
	$ftime=date("Y-m-d H:i:s",$hist);
	$now=date("Y-m-d H:i:s",$to_time);
	fprintf(STDOUT,"#Data from: %s to %s\n",$ftime,$now);
	fprintf(STDERR,"#Data from: %s to %s\n",$ftime,$now);
	fprintf(STDOUT,"format short; fixed_point_format(1); global history; time_from=%u;time_to=%u;date_from='%s';date_to='%s';\n",$hist,$to_time,$ftime,$now);
	$itemcount=0;
	$arrid=1;
	$maxitems=0;
	$delay=false;
	foreach ($items as $item) {
		if (preg_match("*$ritem*",$item->key_) && (!preg_match("*$nritem*",$item->key_)) && ($item->value_type==2 || $item->value_type==3)) {
			fprintf(STDOUT,"#%s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s))\n",$item->host, $item->key_, $item->itemid, $item->value_type, $item->delay, $item->history,(int) ($item->history*24*3600/$item->delay), $item->trends,(int) $item->trends*24);
			$itemid=$item->itemid;
			$host=$api->hostGet(
			  Array(
			  "itemids" => Array($itemid),
			  "output" => "extend"
			  )
			);
			$host=$host[0]->name;
			$h=sprintf("history.%s.i%s",$host,$itemid);
			fprintf(STDOUT,"${h}.id=%s; ${h}.key=\"%s\"; ${h}.delay=%s; ${h}.history=%s;\n",$item->itemid,addslashes($item->key_),$h,$item->delay,$item->history);
			$itemcount++;
			$itemids[]=$item->itemid;
			$hgetarr=array(
				"history" => $item->value_type,
				"itemids" => array($itemid),
				"sortfield" => "clock",
				"output" => "extend",
				"sortorder" => "ASC",
				//"limit" => 1000
				"time_from" => $hist,
				"time_to" => $to_time,
				);
			if ($histapi) {
				$history=$api->historyGet($hgetarr);
			} else {
				$history=historyGet($hgetarr);
			}
			//print_r($history);
			fprintf(STDOUT,"#Got %s values for item %s\n",count($history),$item->key_);
			fprintf(STDOUT,"${h}.x=[");
			foreach ($history as $i=>$k) {
			    fprintf(STDOUT,"%s,",$k->clock);
			}
			fprintf(STDOUT,"];\n");
			fprintf(STDOUT,"${h}.y=[");
			foreach ($history as $i=>$k) {
			    fprintf(STDOUT,"%s,",$k->value);
			}
			fprintf(STDOUT,"];\n");
		}
	}

} catch(Exception $e) {
	echo $e->getMessage();
}

