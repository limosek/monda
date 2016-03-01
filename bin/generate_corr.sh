#!/bin/sh

set -e

if [ -z "$1" ]; then
    echo "$0 graphname [monda_options]"
    exit 2
fi
graph=$1
shift

if ! [ -f "${graph}_ic.txt" ]; then
  echo $(dirname $0)/monda.php ic:matrix "$@"
  $(dirname $0)/monda.php ic:matrix "$@" >${graph}_ic.txt
  $(dirname $0)/monda.php ic:show -Ov expanded "$@" >${graph}_ic.names | head -n 1
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
print("${graph}-corr.png");

EOF

