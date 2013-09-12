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
$time_step=60;
$histapi=true;
$backuptable=false;

$opts=getopt(
	"h:t:H:a:i:SB"
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

if (isset($opts["a"])) {
	$time_step=$opts["a"];
}

if (isset($opts["i"])) {
	$ritem=$opts["i"];
}

try {
	echo "a";
	init_api();
	
	$itemids=Array();
	$history=array();
	$ftime=date("Y-m-d H:i:s",$hist);
	$now=date("Y-m-d H:i:s",$to_time);
	fprintf(STDOUT,"#Data from: %s to %s\n",$ftime,$now);
	fprintf(STDERR,"#Data from: %s to %s\n",$ftime,$now);
	fprintf(STDOUT,"time_from=%u;time_to=%u;date_from='%s',date_to='%s';tmp=[];\n",$hist,$to_time,$ftime,$now);
	$itemcount=0;
	$arrid=1;
	$maxitems=0;
	$delay=false;
	foreach ($items as $item) {
		if (preg_match("*$ritem*",$item->key_) || preg_match("*$ritem*",$item->name)) {
			fprintf(STDOUT,"#%s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s))\n",$item->host,$item->key_,$item->itemid,$item->value_type,$item->delay,$item->history,(int) ($item->history*24*3600/$item->delay),$item->trends,(int) $item->trends*24);
			$itemid=$item->itemid;
			$h="tmp";
			fprintf(STDOUT,"$h.id=%s; $h.key=\"%s\";$h.delay=%s;$h.history=%s;\n",$item->itemid,$item->key_,$item->delay,$item->history);
			$itemcount++;
			$itemids[]=$item->itemid;
			$hgetarr= array(
				"history" => $item->value_type,
				"itemids" => $itemids,
				"sortfield" => "clock",
				"output" => "extend",
				"sortorder" => "ASC",
				//"limit" => 1000
				"time_from" => $hist,
				"time_to" => $to_time,
				);
			if ($histapi) {
				$history[$item->itemid]=$api->historyGet($hgetarr);
			} else {
				$history[$item->itemid]=historyGet($hgetarr);
			}
			
			fprintf(STDOUT,"#Got %s values\n\n",count($history[$item->itemid]));
			fprintf(STDERR,"#Got %s values for item %s\n",count($history[$item->itemid]),$item->key_);
			fprintf(STDOUT,"tmpx=[");
			foreach ($history[$item->itemid] as $i=>$h) {
			    fprintf(STDOUT,"%s,",$h->clock);
			}
			fprintf(STDOUT,"];\n");
			fprintf(STDOUT,"tmpy=[");
			foreach ($history[$item->itemid] as $i=>$h) {
			    fprintf(STDOUT,"%s,",$h->value);
			}
			fprintf(STDOUT,"];\n");
			fprintf(STDOUT,'history.%s.i%s=tmp; history.%s.i%s.x=tmpx; history.%s.i%s.y=tmpy; items=[items,tmp.id];'."\n",$rhost,$item->itemid,$rhost,$item->itemid,$rhost,$item->itemid,$rhost);
		}
	}

} catch(Exception $e) {
	echo $e->getMessage();
}

