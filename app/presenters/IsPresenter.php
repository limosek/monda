<?php

namespace App\Presenters;

use App\Model\ItemStat,
    Tracy\Debugger,
    App\Model\Opts,
    App\Model\CliDebug,
    App\Model\Monda,
    App\Model\Util,
    App\Model\Triggerinfo,
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
                false, "history_interpolate", "Interpolate history data (slow).", true, "yes"
        );
        Opts::addOpt(
                false, "triggerids", "Select this triggerids to history", (int) 0, 0
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
        Opts::optToArray("triggerids", ",");
        if (Opts::getOpt("output_mode")=="arff") {
            Opts::setOpt("item_restricted_chars","{}[],.| ");
        }
        if (Opts::getOpt("events_prefetch")) {
            Opts::setOpt("events_prefetch", Util::timetoseconds(Opts::getOpt("events_prefetch")) - time());
        }
        if (count(Opts::getOpt("itemids"))==0) {
            ItemStat::itemsToIds();
        }
        if (!Opts::getOpt("anonymize_key") && Opts::getOpt("anonymize_items")) {
            self::mexit(2,"You must use --anonymize_key to anonymize items.");
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
                $itxt=Util::anonymize($itxt,Opts::getOpt("anonymize_key"));
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
        if (Opts::getOpt("output_mode")=="brief") {
            self::mexit(3,"This action is possible only with csv output mode.\n");
        }
        $rows = ItemStat::isZabbixHistory();
        if ($rows) {
            $clocks=Array();
            $this->exportdata = $rows;
            $i=0;
            foreach ($this->exportdata as $clock => $row) {
                $i++;
                $clocks[]=$row["clock"];
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
            List($tinfo,$trows)=TriggerInfo::History(Opts::getOpt("triggerids"),$clocks);
            foreach ($tinfo as $t=>$ti) {
                    $this->exportinfo[$t] = $ti["description"];
                    $this->arffinfo[$t] = "{OK,PROBLEM}";
            }
            foreach ($this->exportdata as $i=>$row) {
                foreach ($tinfo as $t=>$ti) {
                    $this->exportdata[$i][$t]=$trows[$i][$t];
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
