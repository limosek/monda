#!/bin/sh

outdir=$(dirname $0)/../out
pdir=$outdir/profile
monda=$(dirname $0)/monda.php
mkdir -p $pdir

env php -d 'xdebug.profiler_enable=1' -d "xdebug.profiler_output_dir=$pdir" $monda "$@" --sql_profile --api_profile

