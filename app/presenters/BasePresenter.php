<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,
        App\Model\Options,
        Tracy\Debugger,
        Nette\Utils\DateTime as DateTime;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
   
    function mexit($code=0,$msg="") {
        if ($code==0) {
            Debugger::log($msg,Debugger::INFO);
        } else {
            Debugger::log($msg,Debugger::ERROR);
        }
        if (!getenv("MONDA_CLI")) {
            if ($code!=0) {
                throw New Exception("Error #$code: $msg");
            } else {
                $this->terminate();
            }
        } else {
            exit($code);
        }
    }

    function beforeRender() {
        Options::read($this->getParameters());
        if (Options::get("configinfo")) {
            dump(Options::get());
            self::mexit();
        }
    }
    
    function renderCli() {
        global $container;
        $httpResponse = $container->getByType('Nette\Http\Response');
        $httpResponse->setContentType('text/csv', 'UTF-8');
        
       foreach ((array) $this->exportdata as $id=>$row) {
           echo "#Row $id (size ".count($row).")\n";
            foreach ($row as $r=>$v) {
                echo "$r='$v'\n";
            }
            echo "\n\n";
        }
        self::mexit();
    }
    
    function renderCsv() {
        global $container;
        $httpResponse = $container->getByType('Nette\Http\Response');
        $httpResponse->setContentType('text/csv', 'UTF-8');
        
        $opts=Model\Monda::$opts;
        $i = 0;
        
        foreach ((array) $this->exportdata as $id => $row) {
            if ($i == 0) {
                foreach ($row as $r => $v) {
                    echo sprintf('%s%s%s;',$opts->csvenc,$r,$opts->csvenc);
                }
                echo "\n";
            }
            $cnt=count($row);
            $j=1;
            foreach ($row as $r => $v) {
                if (is_object($v)) { 
                    if (get_class($v)=="Nette\Utils\DateTime") {
                        $v=$v->format("c");
                    }
                }
                if ($j!=$cnt) {
                    echo sprintf('%s%s%s%s',$opts->csvenc,$v,$opts->csvenc,$opts->csvdelim);
                } else {
                    echo sprintf('%s%s%s',$opts->csvenc,$v,$opts->csvenc);
                }
                $j++;
            }
            echo "\n";
            $i++;
        }
        self::mexit();
    }
    
    function renderDump() {
        var_export($this->exportdata);
        self::mexit();
    }
    
    function renderShow($var) {
        $this->exportdata=$var;
        switch (Model\Monda::$opts->outputmode) {
            case "cli":
                self::renderCli();
                break;
            case "csv":
                self::renderCsv();
                break;
            case "dump":
                self::renderDump();
                break;
            default:
                throw New Nette\Neon\Exception("Unknown output mode!\n");
        }
    }
    
    public function renderDefault() {
        $this->Help();
    }
       
}
