#!/bin/sh

export XDEBUG_CONFIG="idekey=netbeans-xdebug" 
monda=$(dirname $0)/monda.php

env php -dxdebug.remote_autostart=On -dxdebug.remote_enable=on $monda "$@"
