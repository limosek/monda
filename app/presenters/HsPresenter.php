<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class HsPresenter extends TwPresenter
{
    private $hs;
    
    public function Help() {
        echo "
     Host operations
            
     hs:show [common opts]
     hs:update [common opts]
        Update hostids for itemids in monda db
     hs:delete [common opts]
     hs:compute [common opts]
        Compute stats based on host and itemids
     
    [common opts]
    \n";
        $this->helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=$this->parseOpt($ret,
                "hostids",
                "Hi","hostids",
                "Hostids to get",
                false,
                "All"
                );
        if ($ret->hostids) {
            $ret->hostids=preg_split("/,/",$ret->hostids);
        }
        $ret=$this->parseOpt($ret,
                "hostgroups",
                "Hg","hostgroup",
                "Hostgroups to get",
                "monda",
                "monda"
                );
        if ($ret->hostgroups) {
            $ret->hostgroups=preg_split("/,/",$ret->hostgroups);
        }
        $ret=$this->parseOpt($ret,
                "hosts",
                "Hh","hosts",
                "Hostnames to get",
                false,
                "All"
                );
        if ($ret->hosts) {
            $ret->hosts=preg_split("/,/",$ret->hosts);
        }
        $this->hs=New \App\Model\HostStat($ret);
        $ret=$this->hs->hostsToIds($ret);
        return($ret);
    }
    
    public function renderDefault() {
        $this->Help();
        $this->mexit();
    }
    public function renderHs() {
        $this->Help();
        $this->mexit();
    }

    public function renderShow() {
        $hs=New \App\Model\HostStat($this->opts);
        $rows=$hs->hsSearch($this->opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            BasePresenter::renderShow($this->exportdata);
        }
        $this->mexit();
    }
    
    public function renderCompute() {
        $this->hs=New \App\Model\HostStat($this->opts);
        $this->hs->hsMultiCompute($this->opts);
        $this->mexit();
    }
    
    public function renderUpdate() {
        $this->hs=New \App\Model\HostStat($this->opts);
        $this->hs->hsUpdate($this->opts);
        $this->mexit();
    }
    
    public function renderDelete() {
        $this->hs=New \App\Model\HostStat($this->opts);
        $this->hs->hsDelete($this->opts);
        $this->mexit();
    }
}