<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class TwPresenter extends BasePresenter
{
    
    public function renderTw() {
        self::Help();
        self::mexit();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=self::parseOpt($ret,
                "start",
                "s","start",
                "Start time of analysis.",
                date_format(New \DateTime(date("Y-01-01 00:00")),"U"),
                date("Y-01-01 00:00")
                );
        $ret->start=self::timetoseconds($ret->start);
        $ret=self::parseOpt($ret,
                "end",
                "e","end",
                "End time of analysis.",
                $this->roundtime(time()-3600),
                "-1 hour"
                );
        $ret->end=self::timetoseconds($ret->end);
        $ret=self::parseOpt($ret,
                "description",
                "d","window-description",
                "Window description.",
                ""
                );
        $ret=self::parseOpt($ret,
                "length",
                "l","window_length",
                "Window description.",
                "31day,1week,1day,1hour",
                "31day,1week,1day,1hour"
                );
        $ret->length=preg_split("/,/",$ret->length);
        foreach ($ret->length as $id=>$length) {
            if (!is_numeric($length)) {
                $ret->length[$id]=self::timetoseconds($length)-time();
            }
        }
        $ret=self::parseOpt($ret,
                "startalign",
                "ss","align_start",
                "Align start time to be on timewindow boundary (0 minutes for hour, monday for week, 1st day for month)",
                true,
                "yes"
                );
        $ret=self::parseOpt($ret,
                "wsort",
                "ws","windows_sort",
                "Sort order of windows to select ({random|start|length|loi|updated}/{+|-}",
                "start/-",
                "start/-"
                );
        $ret=self::parseOpt($ret,
                "empty",
                "m","only_empty_results",
                "Work only on results which are empty (skip already computed objects)",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "loionly",
                "L","only_with_loi",
                "Select only objects which have loi>0",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "createdonly",
                "c","only_just_created_windows",
                "Select only windows which were just created and contains np data",
                false,
                "no"
                );
        $ret=self::parseOpt($ret,
                "updated",
                "u","windows_updated_before",
                "Select only windows which were updated less than datetime",
                false,
                "no care"
                );
        $ret=self::parseOpt($ret,
                "wids",
                "w","window_ids",
                "Select only windows with this ids",
                false,
                "no care"
                );
        if ($ret->wids) {
            $ret->wids=preg_split("/,/",$ret->wids);
        }
        return($ret);
    }
    
    public function Help() {
        \App\Model\CliDebug::warn("
     Time Window operations
     
     tw:create [common opts]
        Create window(s) for specified period and length

     tw:delete [common opts]
        Remove windows and dependent data from this range
     
    tw:empty [common opts]
        Empty windows data but leave windows created
        
     tw:show
        Show informations about timewindows in db
        
     tw:stats
        Show statistics about timewindows in db
        
     tw:loi
        Recompute Level of Interest for windows
     
     Date formats: @timestamp, YYYYMMDDhhmm, now, '1 day ago', '00:00 1 day ago'
     TimeWindow formats: Date_format/length, Date_format-Date_format/length, id
     If no start and end date given, all data will be affected.
     
    [common opts]
     \n");
        self::helpOpts();
    }
    
    public function renderShow() {
        $windows=\App\Model\Tw::twSearch($this->opts);
        $this->exportdata=$windows->fetchAll();
        parent::renderShow($this->exportdata);
        self::mexit();
    }
    
    public function renderStats() {
        $this->exportdata=\App\Model\Tw::twStats($this->opts);
        parent::renderShow($this->exportdata);
        self::mexit();
    }
    
    public function renderLoi() {
        \App\Model\Tw::twLoi($this->opts);
        self::mexit();
    }
    
    public function renderCreate() {
        \App\Model\Tw::twMultiCreate($this->opts);
        self::mexit();
    }
    
    public function renderDelete() {
        \App\Model\Tw::twDelete($this->opts);
        self::mexit();
    }
    
    public function renderEmpty() {
        \App\Model\Tw::twEmpty($this->opts);
        self::mexit();
    }
}