<?php

if (file_exists('config.inc.php')) {
  require 'config.inc.php';
  if (defined("ZABBIX_CFG")) {
    if (file_exists(ZABBIX_CFG)) {
      require ZABBIX_CFG;
    } else {
      fprintf(STDERR,"Cannot include ".ZABBIX_CFG." \n");
      die();
    }
  }
} else {
  fprintf(STDERR,"Cannot open config.inc.php!\n");
  die();
}

function timetoseconds($t,$r=false) {
    if (is_numeric($t)) {
      return($t);
    } else {
      $dte=date_parse($t);
      if (is_array($dte["relative"])) {
	return(date_format(date_add(New DateTime($r),date_interval_create_from_date_string($t)),"U"));
      } else {
	return(date_format(New DateTime($t),"U"));
      }
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
	  $sql=sprintf("SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s ORDER BY clock",$table,join(",",$itemids),$from,$to);
	  $tq=mysql_query($sql);
	  $lines=Array();
	  while ($row=mysql_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value = $row["value"];
	    $lines[]=$line;
	  }
	  return($lines);
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
	  $sql=sprintf("SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s ORDER BY clock",$table,join(",",$itemids),$from,$to);
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
	    $lines[]=$line;
	  }
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
	  $sql=sprintf("SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s ORDER BY clock",$table,join(",",$itemids),$from,$to);
	  $tq=pg_query($sql);
	  $lines=Array();
	  while ($row=pg_fetch_assoc($tq)) {
	    $line=new StdClass();
	    $line->itemid = $row["itemid"];
	    $line->clock = $row["clock"];
	    $line->value = $row["value"];
	    $lines[]=$line;
	  }
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
	  $sql=sprintf("SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s ORDER BY clock",$table,join(",",$itemids),$from,$to);
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
	    $lines[]=$line;
	  }
	  return($lines);
}

function trendsGet($query) {
    global $DB;
    
    if ($DB["TYPE"]=="MYSQL") {
	return(trendsGetMysql($query));
    } elseif ($DB["TYPE"]=="POSTGRESQL") {
	return(trendsGetPgsql($query));
    }
}

function historyGet($query) {
    global $DB;
    
    if ($DB["TYPE"]=="MYSQL") {
	return(historyGetMysql($query));
    } elseif ($DB["TYPE"]=="POSTGRESQL") {
	return(historyGetPgsql($query));
    }
}

function init_api() {
    global $DB;
    
    if (!defined(ZABBIX_URL) || !defined(ZABBIX_USER) || !defined(ZABBIX_PW)) {
	  $api = new ZabbixApi(ZABBIX_URL, ZABBIX_USER, ZABBIX_PW);
	} else {
	  die("You must define ZABBIX_URL, ZABBIX_USER and ZABBIX_PW macros in config.inc.php!\n");
	}
	
	if ($DB["TYPE"]=="MYSQL") {
	  mysql_connect($DB["SERVER"].":".$DB["PORT"],$DB["USER"],$DB["PASSWORD"]);
	  mysql_select_db($DB["DATABASE"]);
	} elseif ($DB["TYPE"]=="POSTGRESQL") {
	  pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password=%s"),$DB["SERVER"],$DB["PORT"],$DB["DATABASE"],$DB["USER"],$DB["PASSWORD"]);
	}
	return($api);
}

?>