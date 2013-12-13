<?php

// load ZabbixApi
require dirname(__FILE__).'/PhpZabbixApi_Library/ZabbixApiAbstract.class.php';
require dirname(__FILE__).'/PhpZabbixApi_Library/ZabbixApi.class.php';

define("SELECT_QUERY", "SELECT * FROM %s WHERE itemid IN (%s) AND clock>=%s AND clock<=%s");

function errorexit($str, $code) {
    fprintf(STDERR, $str, $code);
    exit($code);
}

$cfgf=dirname(__FILE__).'/config.inc.php';

if (file_exists($cfgf)) {
    require $cfgf;
} else {
    errorexit("Cannot open $cfgf!\n", 3);
}

function timetoseconds($t, $r = false) {
    if (is_numeric($t)) {
        return($t);
    } elseif (preg_match("/(\d\d\d\d)\_(\d\d)\_(\d\d)\_(\d\d)(\d\d)/", $t, $r)) {
        $y = $r[1];
        $m = $r[2];
        $d = $r[3];
        $h = $r[4];
        $M = $r[5];
        $dte = New DateTime("$y-$m-$d $h:$M");
        return(date_format($dte, "U"));
    } else {
        $dte = New DateTime($t);
        return(date_format($dte, "U"));
    }
}

function dumpsql($sql) {
    global $stderr;
    if ($stderr) {
        fprintf(STDERR, "#### $sql\n");
    }
}

function historyGetMysql($query) {
    global $backuptable;

    $type = $query["history"];
    $itemids = $query["itemids"];
    $from = $query["time_from"];
    $to = $query["time_to"];

    if ($type == 0) {
        $table = "history";
    } else {
        $table = "history_uint";
    }
    if ($backuptable) {
        $table.="_backup";
    }
    $sql = sprintf(SELECT_QUERY, $table, join(",", $itemids), $from, $to);
    dumpsql($sql);
    $tq = mysql_query($sql);
    $lines = Array();
    while ($row = mysql_fetch_assoc($tq)) {
        $line = new StdClass();
        $line->itemid = $row["itemid"];
        $line->clock = $row["clock"];
        $line->value = $row["value"];
        $lines[$line->clock . $line->itemid] = $line;
    }
    arsort($lines);
    return($lines);
}

function clocksort($a, $b) {
    if ($a->itemid == $b->itemid) {
        return( ($a->clock < $b->clock) ? -1 : 1);
    } else {
        return( ($a->itemid < $b->itemid) ? -1 : 1);
    }
}

function hostsort($a, $b) {
    return( strcmp($a->name, $b->name));
}

function findeventsbyitem($host, $item) {
    global $triggers, $events;
    foreach ($triggers as $t) {
        if (strstr($t->expression, $host . ":" . $item . ".")) {
            foreach ($events as $e) {
                if ($e->objectid == $t->triggerid) {
                    $ev[] = Array(
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

function item2octave($itemids) {
    global $histapi, $nodata, $itemctx, $hosts, $histapi, $stderr, $api, $hist, $to_time;
    ;

    if (!is_array($itemids))
        $itemids = Array($itemids);

    $hostsfound = true;
    foreach ($itemids as $item) {
        if (array_key_exists($item->itemid, $hosts)) {
            $itemids["host"] = $hosts[$item->itemid];
        } else {
            $hostsfound = false;
        }
        $itemsarr[] = $item->itemid;
    }
    if (!$hostsfound) {
        if ($stderr)
            fprintf(STDERR,"#### Getting hosts info...");
        $hosts = $api->hostGet(
                Array(
                    "itemids" => $itemsarr,
                    "templated_hosts" => false,
                    "output" => "extend"
                )
        );
        if ($stderr)
            fprintf(STDERR,"Done(%u hosts)\n",count($hosts));
        foreach ($hosts as $h) {
            foreach ($h->items as $i) {
                $hostname = $h->name;
                $host = strtr($hostname, "-.", "__");
                $itemctx[$i->itemid] = $host;
            }
        }
    }
    foreach ($itemids as $item) {
        $host = $itemctx[$item->itemid];
        $h = sprintf("hdata.%s.i%s", $host, $item->itemid);
        fprintf(STDOUT, "### %s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s)),histapi=$histapi\n", $host, $item->key_, $item->itemid, $item->value_type, $item->delay, $item->history, (int) ($item->history * 24 * 3600 / $item->delay), $item->trends, (int) $item->trends * 24);
        if ($stderr)
            fprintf(STDERR, "### %s:%s (id: %s, type: %s, freq: %s, hist: %s(max %s), trends: %s(max %s)),histapi=$histapi\n", $host, $item->key_, $item->itemid, $item->value_type, $item->delay, $item->history, (int) ($item->history * 24 * 3600 / $item->delay), $item->trends, (int) $item->trends * 24);
        fprintf(STDOUT, "hdata.%s.ishost=1;${h}.isitem=1;${h}.id=%s; ${h}.key=\"%s\"; ${h}.delay=%s; ${h}.hdata=%s;\n", $host, $item->itemid, addslashes($item->key_), $h, $item->delay, $item->history);
        $hosts[$host] = 1;
    }
    itemdata2octave($itemids, $itemsarr);
}

function itemdata2octave($itemids, $itemsarr) {
    global $stderr, $nodata, $histapi, $itemctx,
            $withevents, $api, $limithistory,
            $hist,$to_time;

    $hgetarr = array(
        "history" => Array(0, 3),
        "itemids" => $itemsarr,
        "sortfield" => "clock",
        "output" => "extend",
        "sortorder" => "ASC",
        "limit" => $limithistory,
        "time_from" => $hist,
        "time_to" => $to_time,
    );
    if ($stderr)
        fprintf(STDERR, "### Getting for items %s...", join(",", $itemsarr));
    $now=microtime(1);
    if (!$nodata) {
        if ($histapi) {
            $history = $api->historyGet($hgetarr);
        } else {
            $history = historyGet($hgetarr);
        }
    } else {
        $history = array();
    }
    if ($histapi)
        uasort($history, 'clocksort');
    if (count($history) == 0) {
        if ($stderr) fprintf(STDERR, "No history data for them!\n");
        return;
    } else {
        if ($stderr)
            fprintf(STDERR, "Got %u values after %f seconds!\n",count($history),microtime(1)-$now);
    }
    foreach ($history as $k) {
        $itemid = $k->itemid;
        $varr[$itemid][]=$k->value;
        $carr[$itemid][]=$k->clock;
    }
    foreach ($itemids as $item) {
        $itemid=$item->itemid;
        $host = $itemctx[$itemid];
        $h = sprintf("hdata.%s.i%s", $host, $itemid);
        fprintf(STDOUT, "{$h}.x=[%s];\n",join(",",$carr[$itemid]));
        fprintf(STDOUT, "{$h}.y=[%s];\n",join(",",$varr[$itemid]));
        if ($withevents) {
            $revents = findeventsbyitem($hostname, $item->key_);
            if (is_array($revents)) {
                fprintf(STDOUT, "${h}.events=[");
                foreach ($revents as $e) {
                    fprintf(STDOUT, "%s,%s,%s,%s;", $e["clock"], $e["value"], $e["priority"], $e["triggerid"]);
                }
                fprintf(STDOUT, "];\n");
            }
        }
    }
}

function trendsGetMysql($query) {
    global $backuptable;

    $type = $query["trends"];
    $itemids = $query["itemids"];
    $from = $query["time_from"];
    $to = $query["time_to"];

    if ($type == 0) {
        $table = "trends";
    } else {
        $table = "trends_uint";
    }
    if ($backuptable) {
        $table.="_backup";
    }
    $sql = sprintf(SELECT_QUERY, $table, join(",", $itemids), $from, $to);
    dumpsql($sql);
    $tq = mysql_query($sql);
    $lines = Array();
    while ($row = mysql_fetch_assoc($tq)) {
        $line = new StdClass();
        $line->itemid = $row["itemid"];
        $line->clock = $row["clock"];
        $line->value_min = $row["value_min"];
        $line->value_avg = $row["value_avg"];
        $line->value_max = $row["value_max"];
        $line->num = $row["num"];
        $lines[$line->clock . $line->itemid] = $line;
    }
    arsort($lines);
    return($lines);
}

function historyGetPgsql($query) {
    global $backuptable;

    $type = $query["history"];
    $itemids = $query["itemids"];
    $from = $query["time_from"];
    $to = $query["time_to"];

    if ($type == 0) {
        $table = "history";
    } else {
        $table = "history_uint";
    }
    if ($backuptable) {
        $table.="_backup";
    }
    $sql = sprintf(SELECT_QUERY, $table, join(",", $itemids), $from, $to);
    dumpsql($sql);
    $tq = pg_query($sql);
    $lines = Array();
    while ($row = pg_fetch_assoc($tq)) {
        $line = new StdClass();
        $line->itemid = $row["itemid"];
        $line->clock = $row["clock"];
        $line->value = $row["value"];
        $lines[$line->clock . $line->itemid] = $line;
    }
    arsort($lines);
    return($lines);
}

function trendsGetPgsql($query) {
    global $backuptable;

    $type = $query["trends"];
    $itemids = $query["itemids"];
    $from = $query["time_from"];
    $to = $query["time_to"];

    if ($type == 0) {
        $table = "trends";
    } else {
        $table = "trends_uint";
    }
    if ($backuptable) {
        $table.="_backup";
    }
    $sql = sprintf(SELECT_QUERY, $table, join(",", $itemids), $from, $to);
    dumpsql($sql);
    $tq = pg_query($sql);
    $lines = Array();
    while ($row = pg_fetch_assoc($tq)) {
        $line = new StdClass();
        $line->itemid = $row["itemid"];
        $line->clock = $row["clock"];
        $line->value_min = $row["value_min"];
        $line->value_avg = $row["value_avg"];
        $line->value_max = $row["value_max"];
        $line->num = $row["num"];
        $lines[$line->clock . $line->itemid] = $line;
    }
    arsort($lines);
    return($lines);
}

function trendsGet($query) {

    if (ZABBIX_DB_TYPE == "MYSQL") {
        return(trendsGetMysql($query));
    } elseif (ZABBIX_DB_TYPE == "POSTGRESQL") {
        return(trendsGetPgsql($query));
    }
}

function historyGet($query) {

    if (ZABBIX_DB_TYPE == "MYSQL") {
        return(historyGetMysql($query));
    } elseif (ZABBIX_DB_TYPE == "POSTGRESQL") {
        return(historyGetPgsql($query));
    } else {
        errorexit("Unknown Zabbix database type " . ZABBIX_DB_TYPE, 13);
    }
}

function init_api() {

    if (!defined(ZABBIX_URL) || !defined(ZABBIX_USER) || !defined(ZABBIX_PW)) {
        $api = new ZabbixApi(ZABBIX_URL, ZABBIX_USER, ZABBIX_PW);
    } else {
        errorexit("You must define ZABBIX_URL, ZABBIX_USER and ZABBIX_PW macros in config.inc.php!\n", 4);
    }

    if (ZABBIX_DB_TYPE == "MYSQL") {
        mysql_connect(ZABBIX_DB_SERVER . ":" . ZABBIX_DB_PORT, ZABBIX_DB_USER, ZABBIX_DB_PASSWORD);
        mysql_select_db(ZABBIX_DB);
    } elseif (ZABBIX_DB_TYPE == "POSTGRESQL") {
        pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password=%s", ZABBIX_DB_SERVER, ZABBIX_DB_PORT, ZABBIX_DB, ZABBIX_DB_USER, ZABBIX_DB_PASSWORD));
    }
    return($api);
}
