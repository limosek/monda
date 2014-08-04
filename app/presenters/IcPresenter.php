<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class IcPresenter extends IsPresenter
{
    
    public function Help() {
        echo "
     ItemCorr operations
            
     ic:show [common opts]
     ic:compute [common opts]
     ic:delete [common opts]
     ic:loi [common opts]

     Hint: You can select from this types of modes:
        --corr {samewindow|samehour|sameday|samedow|all}
            samewindow - correlation of items inside one window
            samehour   - correlation of one item in same hour of day
            samedow    - --//-- same day of the week
     [common opts]
    \n";
        $this->helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=$this->parseOpt($ret,
                "corr",
                "Cr","corr",
                "Selector for windows to correlate with basic windows",
                "samewindow",
                "samewindow"
                );
        $ret=$this->parseOpt($ret,
                "icempty",
                "ICm","only_empty_corr",
                "Work only on results which are empty (skip already computed objects)",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "icloionly",
                "ICL","only_corr_with_loi",
                "Select only objects which have loi>0",
                false,
                "no"
                );
        $ret=$this->parseOpt($ret,
                "maxicrows",
                "ICmr","max_itemcorr_rows",
                "Maximum rows returned by cross-item searching.",
                1000,
                1000
                );
        $ret=$this->parseOpt($ret,
                "timeprecision",
                "Tp","time_precision",
                "Time precision (maximum difference in time for correlation) in seconds",
                5,
                5
                );
        return($ret);
    }
    
    public function renderDefault() {
        $this->Help();
        $this->mexit();
    }
    public function renderIc() {
        $this->Help();
        $this->mexit();
    }

    public function renderShow() {
        $is=New \App\Model\ItemCorr($this->opts);
        $rows=$is->icSearch($this->opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            BasePresenter::renderShow($this->exportdata);
        }
        $this->mexit();
    }
    
    public function renderLoi() {
        $is=New \App\Model\ItemStat($this->opts);
        $is->IsLoi($this->opts);
        $this->mexit();
    }
    
    public function renderCompute() {
        $ic=New \App\Model\ItemCorr($this->opts);
        $ic->IcMultiCompute($this->opts);
        $this->mexit(0,"Done\n");
    }
    
    public function renderDelete() {
        $is=New \App\Model\ItemStat($this->opts);
        $is->IsDelete($this->opts);
        $this->mexit(0,"Done\n");
    }
}