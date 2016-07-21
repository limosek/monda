#!/bin/sh

set -e

if [ -z "$1" ]; then
    echo "$0 prefix [monda_options]"
    exit 2
fi
graph=$1
shift

outdir=$(dirname $0)/../out
monda=$(dirname $0)/monda.php
lrn=${outdir}/${graph}

if which mpirun >/dev/null; then
  mpirun="mpirun -np 4"
fi

tws=$($monda tw:show $@ -ws start/+ | cut -d ' ' -f 1)

for i in $tws; do
    echo $monda is:history -w $i --item_restricted_chars ' ' -Om lrn -Ov expanded $@ 
    $monda is:history -w $i --item_restricted_chars ' ' -Om lrn -Ov expanded $@ >$lrn-$i.lrn
    esomtrn -l $lrn-$i.lrn -c 100 -r 100
    esomrnd -l $lrn-$i.lrn -b $lrn-$i.lrn -w $lrn-$i.wts -z 10 -p $lrn-$i.png
done
