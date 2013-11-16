#!/usr/bin/php
<?php
error_reporting(E_ERROR|E_PARSE);

// load ZabbixApi
require 'PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require 'PhpZabbixApi_Library/ZabbixApi.class.php';
require './common.inc.php';

$hist=time()-3600;
$rhost='';
$rgroup='';
$ritem='';
$nritem='Someabnormalkeywhichdoesnotexists';
$histapi=true;
$backuptable=false;
$nodata=false;
$stderr=false;
$to_time=time();

$opts=getopt(
	"F:f:T:t:H:G:i:I:SBDe"
);

if (!$opts) {
  fprintf(STDERR,"
  gethistory 
   -F timespec          Specify start_time relative to end_time
   -f timespec          Specify start_time relative to now or absolute
   -T timespec          Specify end_time relative to start_time
   -t timespec          Specify end_time relative to now or absolute		example -1day
   -H host              Specify host to get					example 'Zabbix server'
   -G group             Specify host group to get				example Servers
   -i itemregexp        Specify regexp of item keys to accept, 			example 'net|mem'
   -I itemregexp	Specify regexp of item keys to ignore
   -S 			Use SQL instead of API for getting history
   -B			Use backup tables instead of history tables (implies -S)
   -D			Only show what would be done
   -e			Write stderror messages
   
   timespec examples:   -1day, 3day, -8hour, '2013-01-01 00:00', @1379323692
  \n\n");
  errorexit("",1);
}

if (isset($opts["f"])) {
	$hist=timetoseconds($opts["f"]);
} else {
  if (isset($opts["F"])) {
	$hist=timetoseconds($opts["F"])-time()+$to_time;
  }
}

if (isset($opts["t"])) {
	$to_time=timetoseconds($opts["t"]);
} else {
  if (isset($opts["T"])) {
	$to_time=timetoseconds($opts["T"])-time()+$hist;
  }
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

if (isset($opts["i"])) {
	$ritem=$opts["i"];
}

if (isset($opts["I"])) {
	$nritem=$opts["I"];
}

if (isset($opts["D"])) {
	$nodata=true;
}

if (isset($opts["e"])) {
	$stderr=true;
}

try {	
	if ($rhost=="" && $rgroup=="" && $ritem=="") {
	  if ($stderr) fprintf(STDERR,"### Fetching all hosts and items!\n");
	}
	$start=microtime(1);
	$ftime=date("Y-m-d H:i:s",$hist);
	$now=date("Y-m-d H:i:s",$to_time);
	fprintf(STDOUT,"### Data from: %s to %s (actual time %s)\n",$ftime,$now,date("Y-m-d H:i:s"));
	$api=init_api();
	$sq=Array(
	  "output" => 'extend',
	);
	if ($rhost) $sq["host"]=$rhost;
	if ($rgroup) $sq["group"]=$rgroup;
	  if ($stderr) fprintf(STDERR,"### Searching items...\n");
	$items = $api->itemGet($sq);
	if (count($items)==0) {
	  errorexit("No items found!\n",9);
	}
	$itemids=Array();
	$history=array();
	if ($stderr) fprintf(STDERR,"### Data from: %s to %s\n",$ftime,$now);
	if ($to_time<=$hist) {
	  errorexit("End time is lower than start time??\n",6);
	}
	if ($to_time>time()) {
	  errorexit("End time is in future??\n",6);
	}
		
	$sq["time_from"]=$hist;
	$sq["time_to"]=$to_time;
	$sq["select_acknowledges"]="message";
	$sq["selectHosts"]=true;
	$sq["selectRelatedObject"]=true;
	$sq["sortfield"]="clock";
	$events=$api->eventGet($sq);
	$triggerids=Array();
	foreach ($events as $event) {
	  $triggerids[$event->objectid]=$event->objectid;
	}
	$triggers=($api->triggerGet(
	      Array(
		"triggerids"=>$triggerids,
		"expandExpression" => true,
		"output" => "extend"
		)
	    ));
	
	fprintf(STDOUT,"format short; fixed_point_format(1); global hdata; hdata.time_from=%u;hdata.time_to=%u;hdata.date_from='%s';hdata.date_to='%s';\n",$hist,$to_time,$ftime,$now);
	$itemcount=0;
	$valuescount=0;
	$arrid=1;
	$maxitems=0;
	$delay=false;
	$minclock=time();
	$maxclock=0;
	$minclock2=time();
	$maxclock2=0;
	$datafound=false;
	#print_r($events);exit;
	foreach ($items as $item) {
		if (preg_match("*$ritem*",$item->key_) && (!preg_match("*$nritem*",$item->key_)) && ($item->value_type==0 || $item->value_type==3)) {
			$itemid=$item->itemid;
			$host=$api->hostGet(
			  Array(
			  "itemids" => Array($itemid),
			  "output" => "extend"
			  )
			);
			$hostname=$host[0]->name;
			$host=strtr($host[0]->name,"-","_");
			if (trim($host)=="") continue;
			fprintf(STDOUT,"\n\n### %s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s)),histapi=$histapi\n",$host, $item->key_, $item->itemid, $item->value_type, $item->delay, $item->history,(int) ($item->history*24*3600/$item->delay), $item->trends,(int) $item->trends*24);
			if ($stderr) fprintf(STDERR,"\n\n### %s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s)),histapi=$histapi\n",$host, $item->key_, $item->itemid, $item->value_type, $item->delay, $item->history,(int) ($item->history*24*3600/$item->delay), $item->trends,(int) $item->trends*24);
			$h=sprintf("hdata.%s.i%s",$host,$itemid);
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
			if (!$nodata) {
			  if ($histapi) {
				$history=$api->historyGet($hgetarr);
			  } else {
				$history=historyGet($hgetarr);
			  }
			} else {
			      $history=array();
			}
			if (count($history)>10) {
			  $datafound=true;
			  fprintf(STDOUT,"hdata.%s.ishost=1;${h}.isitem=1;${h}.id=%s; ${h}.key=\"%s\"; ${h}.delay=%s; ${h}.hdata=%s;\n",$host,$item->itemid,addslashes($item->key_),$h,$item->delay,$item->history);
			  $revents=findeventsbyitem($hostname,$item->key_);
			  if (is_array($revents)) {
			    fprintf(STDOUT,"${h}.events=[");
			    foreach ($revents as $e) {
			      fprintf(STDOUT,"%s,%s,%s,%s;",$e["clock"],$e["value"],$e["priority"],$e["triggerid"]);
			    }
			    fprintf(STDOUT,"];\n");
			  }
			  fprintf(STDOUT,"### Got %s values for item %s\n",count($history),$item->key_);
			  if ($stderr) fprintf(STDERR,"### Got %s values for item %s\n",count($history),$item->key_);
			  $valuescount+=count($history);
			  fprintf(STDOUT,"${h}.x=[");
			  $c=1;
			  $last=count($history);
			  if ($histapi) {
			    uasort($history,'clocksort');
			  }
			  foreach ($history as $i=>$k) {
			    if ($c==2) {
			      $minclock2=min($k->clock,$minclock2);
			    }
			    if ($c==$last-1) {
			      $maxclock2=max($k->clock,$maxclock2);
			      $minstep=$k->clock-$lastclock;
			      $maxstep=$minstep;
			    }
			    if ($c>1) {
			      $maxstep=max($maxstep,$k->clock-$lastclock);
			      $minstep=min($minstep,$k->clock-$lastclock);
			      $avgstep+=$k->clock-$lastclock;
			    }
			    $minclock=min($k->clock,$minclock);
			    $maxclock=max($k->clock,$maxclock);
			    fprintf(STDOUT,"%s,",$k->clock);
			    $c++;
			    $lastclock=$k->clock;
			  }
			  $avgstep=round($avgstep/$c);
			  fprintf(STDOUT,"];\n");
			  fprintf(STDOUT,"${h}.y=[");
			  foreach ($history as $i=>$k) {
			    fprintf(STDOUT,"%s,",$k->value);
			  }
			  fprintf(STDOUT,"];\n");
			} else {
			  fprintf(STDOUT,"### Got %s values for item %s, ignored!\n",count($history),$item->key_);
			  if ($stderr) fprintf(STDERR,"### Got %s values for item %s, ignored!\n",count($history),$item->key_);
			}
		} else {
			if ($stderr) fprintf(STDERR,"### Ignoring item %s (type %u),regexp=%u,nregex=%u\n",$item->key_,$item->value_type,preg_match("*$ritem*",$item->key_),!preg_match("*$nritem*",$item->key_));
		}
	}
	foreach ($triggers as $t) {
	  fprintf(STDOUT,"hdata.t%s.expression='%s';",$t->triggerid,addslashes($t->expression));
	  fprintf(STDOUT,"hdata.t%s.description='%s';",$t->triggerid,addslashes($t->description));
	  fprintf(STDOUT,"hdata.t%s.priority='%s';",$t->triggerid,$t->priority);
	  fprintf(STDOUT,"hdata.t%s.istrigger=1;\n",$t->triggerid);
	}
	$duration=microtime(1)-$start;
	if ($datafound) {
	  if ($maxstep>4*$avgstep) fprintf(STDERR,"### Probably hole in data (avgstep=%s, maxstep=%s)!.\n",$avgstep);
	  fprintf(STDOUT,"hdata.minx=%s;hdata.minx2=%s;hdata.maxx=%s;hdata.maxx2=%s;hdata.gettime=%s;hdata.minstep=%s;hdata.maxstep=%s;hdata.avgstep=%s;\n",$minclock,$minclock2,$maxclock,$maxclock2,$duration,$minstep,$maxstep,$avgstep);
	  if ($stderr) fprintf(STDERR,"### Took %s seconds to get %i items and %i values.\n",$duration,$itemcount,$valuescount);
	} else {
	  errorexit("No data in history found!\n",15);
	}

} catch(Exception $e) {
	echo $e->getMessage();
}

