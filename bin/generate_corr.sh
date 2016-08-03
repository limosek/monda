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

if ! [ -f "${graph}_ic.txt" ]; then
  echo $monda ic:matrix "$@"
  $monda ic:matrix "$@" >${outdir}/${graph}_ic.txt
  $monda ic:show -Ov expanded "$@" >${outdir}/${graph}_ic.names | head -n 1
fi

octave -q <<EOF

h=dlmread("${graph}_ic.txt");
s=size(h,1);
x=[1:s];
y=[1:s];

f=figure();
set(f,'papertype', 'a4');
colorbar();
surface(x,y,h);
print("${outdir}/${graph}-corr.png");

EOF

