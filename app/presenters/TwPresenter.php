<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class TwPresenter extends BasePresenter
{
    
    public $tw;
    
    public function renderDefault() {
        $this->Help();
        $this->mexit();
    }
    
    public function renderTw() {
        $this->Help();
        $this->mexit();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=$this->parseOpt($ret,
                "start",
                "s","start-datetime",
                "Start time of analysis.",
                date_format(New \DateTime(date("Y-01-01 00:00")),"U"),
                date("Y-01-01 00:00")
                );
        $ret->start=$this->timetoseconds($ret->start);
        $ret=$this->parseOpt($ret,
                "end",
                "e","end-datetime",
                "End time of analysis.",
                $this->roundtime(time()-3600),
                "-1 hour"
                );
        $ret->end=$this->timetoseconds($ret->end);
        $ret=$this->parseOpt($ret,
                "description",
                "d","window-description",
                "Window description.",
                ""
                );
        $ret=$this->parseOpt($ret,
                "length",
                "l","window_length",
                "Window description.",
                "31day,1week,1day,1hour",
                "31day,1week,1day,1hour"
                );
        $ret->length=preg_split("/,/",$ret->length);
        foreach ($ret->length as $id=>$length) {
            if (!is_numeric($length)) {
                $ret->length[$id]=$this->timetoseconds($length)-time();
            }
        }
        $ret=$this->parseOpt($ret,
                "startalign",
                "ss","align_start",
                "Align start time to be on timewindow boundary (0 minutes for hour, monday for week, 1st day for month)",
                true,
                "yes"
                );
        $ret=$this->parseOpt($ret,
                "wsort",
                "ws","windows_sort",
                "Sort order of windows to select ({random|start|length|loi|updated}/{+|-}",
                "start/-",
                "start/-"
                );
        $ret=$this->parseOpt($ret,
                "empty",
                "m","only-empty-windows",
                "Work only on windows which are empty (skip computed windows)",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "loionly",
                "L","only_windows_with_loi",
                "Select only windows which have loi>0",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "createdonly",
                "c","only_just_created_windows",
                "Select only windows which were just created and contains np data",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "updated",
                "u","windows_updated_before",
                "Select only windows which were updated less than datetime",
                false,
                "no care"
                );
        $ret=$this->parseOpt($ret,
                "wids",
                "w","window_ids",
                "Select only windows with this ids",
                false,
                "no care"
                );
        return($ret);
    }
    
    public function Help() {
        echo "
     Time Window operations
     
     tw:create [common opts]
        Create window(s) for specified period and length

     tw:delete [common opts]
        Remove windows and dependent data from this range
        
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
     \n";
        $this->helpOpts();
    }
    
    public function renderShow() {
        $this->tw=New \App\Model\Tw($this->opts);
        $windows=$this->tw->twSearch($this->opts);
        $this->exportdata=$windows->fetchAll();
        parent::renderShow($this->exportdata);
        $this->mexit();
    }
    
    public function renderStats() {
        $this->tw=New \App\Model\Tw($this->opts);
        $this->exportdata=$this->tw->twStats($this->opts);
        parent::renderShow($this->exportdata);
        $this->mexit();
    }
    
    public function renderLoi() {
        $this->tw=New \App\Model\Tw($this->opts);
        $this->tw->twLoi($this->opts);
        $this->mexit();
    }
    
    public function renderCreate() {
        $this->tw=New \App\Model\Tw($this->opts);
        $this->tw->twMultiCreate($this->opts);
        $this->mexit();
    }
    
    public function renderDelete() {
        $this->tw=New \App\Model\Tw($this->opts);
        $this->tw->twDelete($this->opts);
        $this->mexit();
    }
    
    public function renderClean() {
        $this->tw=New \App\Model\Tw($this->opts);
        $this->tw->twClean($this->opts);
        $this->mexit();
    }
}