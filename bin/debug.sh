#!/bin/sh

export XDEBUG_CONFIG="idekey=netbeans-xdebug" 

php5 -dxdebug.remote_autostart=On -dxdebug.remote_enable=on $(dirname $0)/monda.php "$@"

 