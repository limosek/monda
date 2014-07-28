<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class IsPresenter extends HsPresenter
{
    
    public function Help() {
        echo "
     ItemStats operations
            
     is:show [common opts]
     is:compute [common opts]
     is:delete [common opts]
     is:loi [common opts]

     [common opts]
    \n";
        $this->helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=$this->parseOpt($ret,
                "min_values_per_window",
                "Mvw","min_values_per_window",
                "Minimum values for item per window to process",
                10,
                10
                );
        $ret=$this->parseOpt($ret,
                "min_avg_for_cv",
                "Mac","min_avg_for_cv",
                "Minimum average for CV to process",
                0.1,
                0.1
                );
        $ret=$this->parseOpt($ret,
                "itemids",
                "Ii","itemids",
                "Itemids to get",
                false,
                "All"
                );
        if ($ret->itemids) {
            $ret->itemids=preg_split("/,/",$ret->itemids);
        }
        $ret=$this->parseOpt($ret,
                "items",
                "Ik","items",
                "Item keys to get",
                false,
                "All"
                );
        if ($ret->items) {
            $ret->items=preg_split("/,/",$ret->items);
        }
        $is=New \App\Model\ItemStat($ret);
        $ret=$is->itemsToIds($ret);
        return($ret);
    }
    
    public function renderDefault() {
        $this->Help();
        $this->mexit();
    }
    public function renderIs() {
        $this->Help();
        $this->mexit();
    }

    public function renderShow() {
        $is=New \App\Model\ItemStat($this->opts);
        $this->exportdata=$is->isSearch($this->opts)->fetchAll();
        parent::renderShow($this->exportdata);
        $this->mexit();
    }
    
    public function renderLoi() {
        $is=New \App\Model\ItemStat($this->opts);
        $is->IsLoi($this->opts);
        $this->mexit();
    }
    
    public function renderCompute() {
        $is=New \App\Model\ItemStat($this->opts);
        //dump($this->opts);
        $is->IsMultiCompute($this->opts);
        $this->mexit(0,"Done\n");
    }
}