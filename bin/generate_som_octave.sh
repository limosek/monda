#!/bin/sh

set -e

if [ -z "$1" ]; then
    echo "$0 graphname [monda_options]"
    exit 2
fi
graph=$1
shift

outdir=$(dirname $0)/../out
monda=$(dirname $0)/monda.php
graph=${outdir}/${graph}

if ! [ -f "${graph}.txt" ]; then
  $monda is:history \
    -Om st "$@" >${graph}.txt 
fi

octave -q <<EOF
warning('off');
addpath("./somtoolbox/");
D=som_read_data("${graph}.txt");
sM=som_make(D);
som_show(sM);
print("${graph}-som.png");

EOF

