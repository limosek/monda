#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_PARSE);
define(GH_VERSION, "3");

require dirname(__FILE__).'/common.inc.php';

$hist = time() - 3600;
$rhost = '';
$rgroup = '';
$ritem = '';
$nritem = 'Someabnormalkeywhichdoesnotexists';
$histapi = true;
$backuptable = false;
$nodata = false;
$stderr = false;
$allatonce = 1;
$withtriggers = 0;
$withevents = 0;
$to_time = time();
$limithistory=1000000;

$opts = getopt(
        "F:f:T:t:H:G:i:I:SBDeO:TE"
);

if (!$opts) {
    fprintf(STDERR, "
  gethistory 
   -F timespec          Specify start_time relative to end_time
   -f timespec          Specify start_time relative to now or absolute
   -T timespec          Specify end_time relative to start_time
   -t timespec          Specify end_time relative to now or absolute		example -1day
   -H host              Specify host to get					example 'Zabbix server'
   -G group             Specify host group to get				example Servers
   -i itemregexp        Specify regexp of item keys to accept, 			example 'net|mem'
   -I itemregexp	Specify regexp of item keys to ignore
   -O numitems          Fetch all history at once if number of items to fetch is less than numitems (needs lot of memory). Default item by item
   -L limit             Limit number of history to get at once
   -E                   Fetch all events glued to triggers 
   -S 			Use SQL instead of API for getting history
   -B			Use backup tables instead of history tables (implies -S)
   -D			Only show what would be done
   -e			Write stderror messages
   
   timespec examples:   -1day, 3day, -8hour, '2013-01-01 00:00', @1379323692
  \n\n");
    errorexit("", 1);
}

if (isset($opts["f"])) {
    $hist = timetoseconds($opts["f"]);
} else {
    if (isset($opts["F"])) {
        $hist = timetoseconds($opts["F"]) - time() + $to_time;
    }
}

if (isset($opts["t"])) {
    $to_time = timetoseconds($opts["t"]);
} else {
    if (isset($opts["T"])) {
        $to_time = timetoseconds($opts["T"]) - time() + $hist;
    }
}

if (isset($opts["S"])) {
    $histapi = false;
}

if (isset($opts["B"])) {
    $backuptable = true;
    $histapi = false;
}

if (isset($opts["H"])) {
    $rhost = $opts["H"];
}

if (isset($opts["G"])) {
    $rgroup = $opts["G"];
}

if (isset($opts["O"])) {
    $allatonce=$opts["O"];
}

if (isset($opts["L"])) {
    $limithistory=$opts["L"];
}

if (isset($opts["E"])) {
    $withevents=1;
    $withtriggers=1;
}

if (isset($opts["i"])) {
    $ritem = addslashes($opts["i"]);
}

if (isset($opts["I"])) {
    $nritem = addslashes($opts["I"]);
}

if (isset($opts["D"])) {
    $nodata = true;
}

if (isset($opts["e"])) {
    $stderr = true;
}

try {
    if ($rhost == "" && $rgroup == "" && $ritem == "") {
        if ($stderr)
            fprintf(STDERR, "### Fetching all hosts and items!\n");
    }
    $start = microtime(1);
    $ftime = date("Y-m-d H:i:s", $hist);
    $now = date("Y-m-d H:i:s", $to_time);
    fprintf(STDOUT, "### Data from: %s to %s (actual time %s)\n", $ftime, $now, date("Y-m-d H:i:s"));
    $api = init_api();
    $sq = Array(
        "output" => 'extend',
        "monitored" => true
    );
    if ($rhost)
        $sq["host"] = $rhost;
    if ($rgroup)
        $sq["group"] = $rgroup;
    $now=microtime(1);
    if ($stderr)
        fprintf(STDERR, "### Searching items...");
    $items = $api->itemGet($sq);
    if (count($items) == 0) {
        errorexit("No items found!\n", 9);
    }
    if ($stderr)
        fprintf(STDERR, "Got %u items if %f seconds\n",count($items),microtime(1)-$now);
    $itemids = Array();
    $history = array();
    if ($stderr)
        fprintf(STDERR, "### Data from: %s to %s\n", $ftime, $now);
    if ($to_time <= $hist) {
        errorexit("End time is lower than start time??\n", 6);
    }
    if ($to_time > time()) {
        errorexit("End time is in future??\n", 6);
    }

    $sq["time_from"] = $hist;
    $sq["time_to"] = $to_time;
    $sq["select_acknowledges"] = "message";
    $sq["selectHosts"] = true;
    $sq["selectRelatedObject"] = true;
    $sq["sortfield"] = "clock";
    if ($withevents) {
        if ($stderr) fprintf(STDERR,"#### Getting events...");
        $events = $api->eventGet($sq);
        $triggerids = Array();
        foreach ($events as $event) {
            $triggerids[$event->objectid] = $event->objectid;
        }
        if ($withtriggers) {
            if ($stderr) fprintf(STDERR,",triggers...");
            $triggers = ($api->triggerGet(
                    Array(
                        "triggerids" => $triggerids,
                        "expandExpression" => true,
                        "output" => "extend"
                    )
            ));
        } else {
            $triggers=Array();
        }
        if ($stderr) fprintf(STDERR,"(%u triggers, %u events)\n",count($triggers),count($events));
    } else {
        $events=Array();
    }
    fprintf(STDOUT, "global hdata; hdata.version=%s; hdata.time_from=%u;hdata.time_to=%u;hdata.date_from='%s';hdata.date_to='%s';\n", GH_VERSION, $hist, $to_time, $ftime, $now);
    $itemcount = 0;
    $origitems=count($items);
    foreach ($items as $k=>$item) {
        if (preg_match("*$ritem*", $item->key_) && (!preg_match("*$nritem*", $item->key_)) && ($item->value_type == 0 || $item->value_type == 3)) {
            $itemid = $item->itemid;
            $itemcount++;
            $itemids[] = $item->itemid;
        } else {
            unset($items[$k]);
            if ($stderr)
                fprintf(STDERR, "### Ignoring item %s (type %u),regexp=%u,nregex=%u\n", $item->key_, $item->value_type, preg_match("*$ritem*", $item->key_), !preg_match("*$nritem*", $item->key_));
        }
    }
    if (count($items)>$allatonce) {
        if ($stderr)
                fprintf(STDERR, "### Fetching item by item (orig %u, fetching %u)\n",$origitems,count($items));
        $allatonce=false;
    } else {
        if ($stderr)
                fprintf(STDERR, "### Fetching all at once (orig %u, fetching %u)\n",$origitems,count($items));
        $allatonce=true;
    }
    if (!$allatonce) {
        foreach ($items as $item) {
            item2octave($item);
        }
    } else {
        item2octave($items);
    }
    if ($withtriggers) {
        foreach ($triggers as $t) {
            fprintf(STDOUT, "hdata.t%s.expression='%s';", $t->triggerid, addslashes($t->expression));
            fprintf(STDOUT, "hdata.t%s.description='%s';", $t->triggerid, addslashes($t->description));
            fprintf(STDOUT, "hdata.t%s.priority='%s';", $t->triggerid, $t->priority);
            fprintf(STDOUT, "hdata.t%s.istrigger=1;\n", $t->triggerid);
        }
    }
    $duration = microtime(1) - $start;
} catch (Exception $e) {
    echo $e->getMessage();
}

