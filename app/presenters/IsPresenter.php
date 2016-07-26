<?php

namespace App\Presenters;

use App\Model\ItemStat,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    App\Model\Monda,
    Nette\Utils\DateTime as DateTime;

class IsPresenter extends BasePresenter {

    public function Help() {
        CliDebug::warn("
     ItemStats operations
            
     is:show [common opts]
     is:stats [common opts]
     is:history [common opts]
     is:compute [common opts]
     is:delete [common opts]
     is:loi [common opts]

     [common opts]
    \n");
        Opts::helpOpts();
        Opts::showOpts();
        echo "\n";
        self::mexit();
    }

    public function startup() {
        parent::startup();
        HsPresenter::startup();
        
        Opts::addOpt(
                false, "min_values_per_window", "Minimum values for item per window to process", 20, 20
        );
        Opts::addOpt(
                false, "min_avg_for_cv", "Minimum average for CV to process", 0.01, 0.01
        );
        Opts::addOpt(
                false, "min_stddev", "Minimum stddev of values to process. Only bigger stddev will be processed", 0, 0
        );
        Opts::addOpt(
                false, "min_cv", "Minimum CV to process values.", 0.01, 0.01
        );
        Opts::addOpt(
                false, "max_cv", "Maximum CV to process values.", 100, 100
        );
        Opts::addOpt(
                false, "is_minloi", "Minimum itemstat loi to search.", 0, 0
        );
        Opts::addOpt(
                false, "itemids", "Itemids to get", false, "All"
        );
        Opts::addOpt(
                false, "history_granularity", "Granularity of history data to fetch in seconds.", 600, 600
        );
        Opts::addOpt(
                false, "triggerids_history", "Add this triggerids to history", false, false
        );
        Opts::addOpt(
                false, "events_prefetch", "Prefetch this number of seconds before history dump", Monda::_1WEEK, "1 week"
        );
        Opts::addOpt(
                false, "max_windows_per_query", "Maximum number of windows per one sql query", 10, 10
        );
        Opts::addOpt(
                false, "items", "Item keys to get. Use ~ to add more items. Prepend item by @ to use regex.", false, "All"
        );
        Opts::addOpt(
                false, "anonymize_items", "Anonymize item names", false, "no"
        );
        Opts::addOpt(
                false, "item_restricted_chars", "Characters mangled in items", false, "none"
        );
        
        Opts::setDefaults();
        Opts::readCfg(Array("Is"));
        Opts::readOpts($this->params);
        self::postCfg();
        if ($this->action=="stats") {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("itemid","avg_","loi","wcnt"));
            }
        } else {
            if (Opts::isDefault("brief_columns")) {
                Opts::setOpt("brief_columns",Array("itemid","stddev_","cv","loi"));
            }
        }
    }

    static function postCfg() {
        HsPresenter::postCfg();
        Opts::optToArray("itemids");
        Opts::optToArray("items", "~");
        Opts::optToArray("triggerids_history", ",");
        if (Opts::getOpt("output_mode")=="arff") {
            Opts::setOpt("item_restricted_chars","[],. ");
        }
        if (count(Opts::getOpt("itemids"))==0) {
            ItemStat::itemsToIds();
        }
        if (!Opts::getOpt("anonymize_key") && Opts::getOpt("anonymize_items")) {
            self::mexit(2,"You must use anonymize_key to anonymize items.");
        }
    }
    
    static function expandItemParams($item) {
        if (preg_match("/\[(.*)\]/",$item[0]->key_,$params)) {
            $params=preg_split("/,/",$params[1]);
            foreach ($params as $i=>$p) {
                $item[0]->name=str_replace('$'.($i+1),$p,$item[0]->name);
            }
        }
        return($item);
    }

    static function expandItem($itemid, $withhost = false, $desc = false) {
        $ii = ItemStat::itemInfo($itemid);
        $ii=self::expandItemParams($ii);
        if (count($ii) > 0) {
            if ($desc) {
                $itxt = $ii[0]->name;
            } else {
                $itxt = $ii[0]->key_;
            }
            if (Opts::getOpt("anonymize_items")) {
                $itxt=Util::encrypt($itxt,Opts::getOpt("anonymize_key"));
            } else {
                if (Opts::getOpt("item_restricted_chars")) {
                    $itxt=strtr($itxt,Opts::getOpt("item_restricted_chars"),"_____________");
                }
            }
            if ($withhost) {
                return(HsPresenter::expandHost($ii[0]->hostid) . ":" . $itxt);
            } else {
                return($itxt);
            }
        } else {
            return("unknown");
        }
    }

    public function renderShow() {
        $rows = ItemStat::isSearch();
        if ($rows && $rows->getRowCount()>0) {
            $this->exportdata = $rows->fetchAll();
            if (Opts::getOpt("output_verbosity") == "expanded") {
                foreach ($this->exportdata as $i => $row) {
                    CliDebug::dbg(sprintf("Processing %d row of %d          \r", $i, count($this->exportdata)));
                    $row["host"] = HsPresenter::expandHost($row->hostid);
                    $row["key"] = self::expandItem($row->itemid);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        } else {
            CliDebug::warn("No rows found. Try to fine-tune parameters (is_minloi, tw_minloi, window_empty, ...).\n");
        }
        self::mexit();
    }

    public function renderHistory() {
        if (!Opts::getOpt("itemids")) {
            self::mexit("You must use --items parameter to select items!\n");
        }
        $rows = ItemStat::isZabbixHistory();
        if (Opts::getOpt("triggerids_history")) {
            $eq = Array(
                "time_from" => Opts::getOpt("start") - Opts::getOpt("events_prefetch"),
                "time_till" => Opts::getOpt("end"),
                "output" => "extend",
                "objectids" => Opts::getOpt("triggerids_history"),
                "selectHosts" => "refer",
                "selectRelatedObject" => "refer",
                "select_alerts" => "refer",
                "select_acknowledges" => "refer",
                "sortfield" => "clock"
            );
            $events = Monda::apiCmd("eventGet", $eq);
            CliDebug::warn(sprintf("Found %d events for triggerids %s.\n", count($events), join(",", Opts::getOpt("triggerids_history"))));
        }
        if ($rows) {
            $clocks = Array();
            $this->exportdata = array_values($rows);
            foreach ($this->exportdata as $i => $row) {
                $clocks[$row["clock"]] = $row["clock"];
                CliDebug::dbg(sprintf("Processing %d row of %d      \r", $i, count($this->exportdata)));
                foreach ($row as $column => $value) {
                    if (!array_key_exists($column, $this->exportinfo)) {
                        if ($column=="clock") {
                            $this->exportinfo[$column] = "clock";
                        } else {
                            $this->exportinfo[$column] = self::expandItem($column, true);
                        }
                        $this->arffinfo[$column] = "NUMERIC";
                    }
                }
            }
            if (Opts::getOpt("triggerids_history")) {
                $row = 0;
                $triggers = Array();
                $values = Array();
                $firstclock = each($clocks);
                reset($clocks);
                foreach (Opts::getOpt("triggerids_history") as $trigger) {
                    $this->exportinfo["trg_".$trigger] = "trg_".$trigger;
                    $this->arffinfo["trg_".$trigger] = "{OK,PROBLEM}";
                }
                foreach ($events as $e) {
                    if ($e->clock < $firstclock) {
                        $values[$e->relatedObject->triggerid] = $e->value;
                    }
                }
                if (count($values) < count(Opts::getOpt("triggerids_history"))) {
                    self::mexit("Use longer prefetch (--events_prefetch)!\n");
                }
                foreach ($clocks as $clock) {
                    foreach ($events as $e) {
                        if ($e->clock <= $clock && $e->clock >= $clock - Opts::getOpt("events_prefetch")) {
                            CliDebug::dbg("Event $e->eventid, clock $e->clock, value $e->value\n");
                            $triggers[$clock][$e->relatedObject->triggerid] = $e->value;
                            $values[$e->relatedObject->triggerid] = $e->value;
                        } else {
                            CliDebug::dbg("Event $e->eventid, clock $e->clock, value $e->value\n");
                            $triggers[$clock][$e->relatedObject->triggerid] = $values[$e->relatedObject->triggerid];
                        }
                    }
                }
            }
            foreach ($this->exportdata as $i => $row) {
                foreach (Opts::getOpt("triggerids_history") as $trigger) {
                    if ($triggers[$row["clock"]][$trigger]) {
                        $this->exportdata[$i]["trg_".$trigger] = "PROBLEM";
                    } else {
                        $this->exportdata[$i]["trg_".$trigger] = "OK";
                    }
                }
            }
            parent::renderShow($this->exportdata);
        }
    }

    public function renderStats() {
        $rows = ItemStat::isStats();
        if ($rows) {
            $this->exportdata = $rows;
            if (Opts::getOpt("output_verbosity") == "expanded") {
                foreach ($this->exportdata as $i => $row) {
                    CliDebug::dbg(sprintf("Processing %d row of %d          \r", $i, count($this->exportdata)));
                    $row["key"] = self::expandItem($row->itemid, true);
                    $this->exportdata[$i] = $row;
                }
            }
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }

    public function renderLoi() {
        ItemStat::IsLoi();
        self::mexit();
    }

    public function renderCompute() {
        ItemStat::IsMultiCompute();
        self::mexit(0, "Done\n");
    }

    public function renderDelete() {
        ItemStat::IsDelete();
        self::mexit(0, "Done\n");
    }

}
