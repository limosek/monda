<?php

namespace App\Presenters;

use Nette\Application\Responses\TextResponse,
    Nette\Security\AuthenticationException,
    Model,
    Nette\Application\UI,
    \App\Model\CliLogger,
    \Tracy\Debugger,
    \App\Model\Options,
    Nette\Utils\DateTime as DateTime;

class ErrorPresenter extends DefaultPresenter {
    
    public function renderDefault() {
        CliLogger::log("\nSomething bad!\n",  CliLogger::ERROR);
        if ($this->params["exception"]->getCode()==404) {
            BasePresenter::Help();
        }
    }

}

?>
