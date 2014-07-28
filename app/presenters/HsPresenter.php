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
     hs:delete [common opts]
     hs:compute [common opts]
     
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
        $ret=$this->hs->hsToIds($ret);
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
        $is=New \App\Model\HostStat($this->opts);
        //dump($this->opts);exit;
    }
    
    public function renderCompute() {
        $this->hs=New \App\Model\HostStat($this->opts);
        foreach ($this->hs->opts->hostids as $hostid) {
            $this->hs->hsCompute($hostid,$this->opts);
        }
        $this->mexit();
    }
}