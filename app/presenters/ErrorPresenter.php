<?php

namespace App\Presenters;

use Nette;
use Tracy\ILogger;
use App\Model\CliDebug;
use App\Model\Opts;


class ErrorPresenter extends DefaultPresenter implements Nette\Application\IPresenter
{
	/** @var ILogger */
	private $logger;


	public function __construct(ILogger $logger)
	{
		$this->logger = $logger;
	}


	/**
	 * @return Nette\Application\IResponse
	 */
	public function run(Nette\Application\Request $request)
	{
		$e = $request->getParameter('exception');

		if ($e instanceof Nette\Application\BadRequestException) {
			// $this->logger->log("HTTP code {$e->getCode()}: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}", 'access');
			return new Nette\Application\Responses\ForwardResponse($request->setPresenterName('Error4xx'));
		}

		$this->logger->log($e, ILogger::EXCEPTION);
                //debug_print_backtrace();
		BasePresenter::mexit($e->getCode(),$e->getMessage()."\n");
	}
}

class Error4xxPresenter extends ErrorPresenter {
    
    public function run(Nette\Application\Request $request) {
        global $argv;
        
        $e = $request->getParameter('exception');
        CliDebug::err($e->getMessage());
        if ($argv[1]=="is") {
            IsPresenter::Help();
        } elseif ($argv[1]=="tw") {
            TwPresenter::Help();
        } elseif ($argv[1]=="ic") {
            IcPresenter::Help();
        } elseif ($argv[1]=="ec") {
            EcPresenter::Help();
        } elseif ($argv[1]=="cron") {
            CronPresenter::Help();
        } elseif ($argv[1]=="hs") {
            HsPresenter::Help();
        } elseif ($argv[1]=="gm") {
            GmPresenter::Help();
        } else {
            DefaultPresenter::Help();
        }
    }

}