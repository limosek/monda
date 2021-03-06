<?php

namespace App;

use Nette,
    Nette\Application\Routers\RouteList,
    Nette\Application\Routers\Route,
    Nette\Application\Routers\CliRouter,
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
        if (getenv("MONDA_CLI")) {
           $router[] = new CliRouter(
                   Array('action' => 'Default:default')
                    );
        } else {
            $router[] = new Route('/', 'Map:tl', Route::ONE_WAY);
            $router[] = new Route('/<presenter>/<action>', 'Default:default');
        }
        return $router;
    }

}
