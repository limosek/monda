<?php

define("SELECT_QUERY","SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s");

function errorexit($str,$code) {
  fprintf(STDERR,$str,$code);
  exit($code);
}

if (file_exists('config.inc.php')) {
  require 'config.inc.php';
} else {
  errorexit("Cannot open config.inc.php!\n",3);
}

function timetoseconds($t,$r=false) {
    if (is_numeric($t)) {
      return($t);
    } else {
      $dte=New DateTime($t);
      return(date_format($dte,"U"));
    }
}

function dumpsql($sql) {
    global $stderr;
    if ($stderr) {
	fprintf(STDERR,"#### $sql\n");
    }
}

function historyGetMysql($query) {
	  global $backuptable;
	  
	  $type=$query["history"];
	  $itemids=$query["itemids"];
	  $from=$query["time_from"];
	  $to=$query["time_to"];
	  
	  if ($type==0) {
	    $table="history";
	  } else {
	    $table="history_uint";
	  }
	  if ($backuptable) {
	    $table.="_backup";
	  }
	  $sql=sprintf(SELECT_QUERY,$table,join(",",$itemids),$from,$to);
	  dumpsql($sql);
	  $tq=mysql_query($sql);
	  $lines=Array();
	  while ($row=mysql_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value = $row["value"];
	    $lines[$line->clock.$line->itemid]=$line;
	  }
	  arsort($lines);
	  return($lines);
}

function clocksort($a, $b) {
    return( ($a->clock <$b->clock) ? -1:1);
}

function hostsort($a, $b) {
    return( strcmp($a->name,$b->name));
}

function findeventsbyitem($host,$item) {
      global $triggers,$events;
      foreach ($triggers as $t) {
	if (strstr($t->expression,$host.":".$item.".")) {
	  foreach ($events as $e) {
	    if ($e->objectid==$t->triggerid) {
	      $ev[]=Array(
	        "clock" => $e->clock,
	        "priority" => $t->priority,
	        "value" => $e->value,
	        "triggerid" => $t->triggerid
	      );
	    }
	  }
	}
      }
      return($ev);
}

function trendsGetMysql($query) {
	  global $backuptable;
	  
	  $type=$query["trends"];
	  $itemids=$query["itemids"];
	  $from=$query["time_from"];
	  $to=$query["time_to"];
	  
	  if ($type==0) {
	    $table="trends";
	  } else {
	    $table="trends_uint";
	  }
	  if ($backuptable) {
	    $table.="_backup";
	  }
	  $sql=sprintf(SELECT_QUERY,$table,join(",",$itemids),$from,$to);
	  dumpsql($sql);
	  $tq=mysql_query($sql);
	  $lines=Array();
	  while ($row=mysql_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value_min = $row["value_min"];
	    $line->value_avg = $row["value_avg"];
	    $line->value_max = $row["value_max"];
	    $line->num = $row["num"];
	    $lines[$line->clock.$line->itemid]=$line;
	  }
	  arsort($lines);
	  return($lines);
}

function historyGetPgsql($query) {
	  global $backuptable;
	  
	  $type=$query["history"];
	  $itemids=$query["itemids"];
	  $from=$query["time_from"];
	  $to=$query["time_to"];
	  
	  if ($type==0) {
	    $table="history";
	  } else {
	    $table="history_uint";
	  }
	  if ($backuptable) {
	    $table.="_backup";
	  }
	  $sql=sprintf(SELECT_QUERY,$table,join(",",$itemids),$from,$to);
	  dumpsql($sql);
	  $tq=pg_query($sql);
	  $lines=Array();
	  while ($row=pg_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value = $row["value"];
	    $lines[$line->clock.$line->itemid]=$line;
	  }
	  arsort($lines);
	  return($lines);
}

function trendsGetPgsql($query) {
	  global $backuptable;
	  
	  $type=$query["trends"];
	  $itemids=$query["itemids"];
	  $from=$query["time_from"];
	  $to=$query["time_to"];
	  
	  if ($type==0) {
	    $table="trends";
	  } else {
	    $table="trends_uint";
	  }
	  if ($backuptable) {
	    $table.="_backup";
	  }
	  $sql=sprintf(SELECT_QUERY,$table,join(",",$itemids),$from,$to);
	  dumpsql($sql);
	  $tq=pg_query($sql);
	  $lines=Array();
	  while ($row=pg_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value_min = $row["value_min"];
	    $line->value_avg = $row["value_avg"];
	    $line->value_max = $row["value_max"];
	    $line->num = $row["num"];
	    $lines[$line->clock.$line->itemid]=$line;
	  }
	  arsort($lines);
	  return($lines);
}

function trendsGet($query) {
    
    if (ZABBIX_DB_TYPE=="MYSQL") {
	return(trendsGetMysql($query));
    } elseif (ZABBIX_DB_TYPE=="POSTGRESQL") {
	return(trendsGetPgsql($query));
    }
}

function historyGet($query) {
    
    if (ZABBIX_DB_TYPE=="MYSQL") {
	return(historyGetMysql($query));
    } elseif (ZABBIX_DB_TYPE=="POSTGRESQL") {
	return(historyGetPgsql($query));
    } else {
	errorexit("Unknown Zabbix database type ".ZABBIX_DB_TYPE,13);
    }
}

function init_api() {
    
    if (!defined(ZABBIX_URL) || !defined(ZABBIX_USER) || !defined(ZABBIX_PW)) {
	  $api = new ZabbixApi(ZABBIX_URL, ZABBIX_USER, ZABBIX_PW);
	} else {
	  errorexit("You must define ZABBIX_URL, ZABBIX_USER and ZABBIX_PW macros in config.inc.php!\n",4);
	}
	
	if (ZABBIX_DB_TYPE=="MYSQL") {
	  mysql_connect(ZABBIX_DB_SERVER.":".ZABBIX_DB_PORT,ZABBIX_DB_USER,ZABBIX_DB_PASSWORD);
	  mysql_select_db(ZABBIX_DB);
	} elseif (ZABBIX_DB_TYPE=="POSTGRESQL") {
	  pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password=%s",ZABBIX_DB_SERVER,ZABBIX_DB_PORT,ZABBIX_DB,ZABBIX_DB_USER,ZABBIX_DB_PASSWORD));
	}
	return($api);
}
