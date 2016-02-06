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
        $router = new RouteList();
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $secured = Route::SECURED;
        } else {
            $secured=0;
        }
        
        if (getenv("MONDA_CLI")) {
            $router[] = new Nette\Application\Routers\CliRouter(
                        array('action' => 'Default'
                            )
            );
        }
        $router[] = new Route('/monda/<presenter>/<action>',
                array(
                 'presenter' => 'Html',
                 'action' => 'tl',
                ),$secured);
        return $router;
    }

}
