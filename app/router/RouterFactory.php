<?php

namespace App;

use Nette,
    Nette\Application\Routers\RouteList,
    Nette\Application\Routers\Route,
    Nette\Application\Routers\SimpleRouter;

/**
 * Router factory.
 */
class RouterFactory {

    /**
     * @return \Nette\Application\IRouter
     */
    public function createRouter() {
        global $argv;
        
        $router = new RouteList();
        if (getenv("MONDA_CLI")) {
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('help', 'action' => 'Default'
                    )
            );
           $router[] = new Nette\Application\Routers\CliRouter(
                    'taw[:<action>]', ['presenter' => 'Tw', 'action' => 'default']
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('is', 'action' => 'ItemStat'
                    )
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('ic', 'action' => 'Ic'
                    )
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('hs', 'action' => 'HostStat'
                    )
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('gm', 'action' => 'Gm'
                    )
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('ec', 'action' => 'Ec'
                    )
            );
            $router[] = new Nette\Application\Routers\CliRouter(
                    array('cron', 'action' => 'Cron'
                    )
            );
        }

        return $router;
    }

}
