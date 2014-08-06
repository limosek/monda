<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model, Nette\Application\UI;

class HsPresenter extends BasePresenter
{
    private $hs;
    
    public function Help() {
        \App\Model\CliDebug::warn("
     Host operations
            
     hs:show [common opts]
     hs:update [common opts]
        Update hostids for itemids in monda db
     hs:delete [common opts]
     hs:compute [common opts]
        Compute stats based on host and itemids
     
    [common opts]
    \n");
        self::helpOpts();
    }
    
    public function getOpts($ret) {
        $ret=parent::getOpts($ret);
        $ret=TwPresenter::getOpts($ret);
        $ret=self::parseOpt($ret,
                "hostids",
                "Hi","hostids",
                "Hostids to get",
                false,
                "All"
                );
        if ($ret->hostids) {
            $ret->hostids=preg_split("/,/",$ret->hostids);
        }
        $ret=self::parseOpt($ret,
                "hostgroups",
                "Hg","hostgroup",
                "Hostgroups to get",
                "monda",
                "monda"
                );
        if ($ret->hostgroups) {
            $ret->hostgroups=preg_split("/,/",$ret->hostgroups);
        }
        $ret=self::parseOpt($ret,
                "hosts",
                "Hh","hosts",
                "Hostnames to get",
                false,
                "All"
                );
        if ($ret->hosts) {
            $ret->hosts=preg_split("/,/",$ret->hosts);
        }
        $ret=\App\Model\HostStat::hostsToIds($ret);
        return($ret);
    }
    
    public function renderHs() {
        self::Help();
        self::mexit();
    }
    
    function expandHost($hostid) {
        $iq = Array(
                "monitored" => true,
                "output" => "extend",
                "hostids" => array($hostid)
            );
        $h=\App\Model\Monda::apiCmd("hostGet",$iq);
        if (count($h)>0) {
            return($h[0]->host);
        } else {
            return("unknown");
        }
    }

    public function renderShow() {
        $rows=  \App\Model\HostStat::hsSearch($this->opts);
        if ($rows) {
            $this->exportdata=$rows->fetchAll();
            parent::renderShow($this->exportdata);
        }
        self::mexit();
    }
    
    public function renderCompute() {
        \App\Model\HostStat::hsMultiCompute($this->opts);
        self::mexit();
    }
    
    public function renderUpdate() {
        \App\Model\HostStat::hsUpdate($this->opts);
        self::mexit();
    }
    
    public function renderDelete() {
        \App\Model\HostStat::hsDelete($this->opts);
        self::mexit();
    }
}