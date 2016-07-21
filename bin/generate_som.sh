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

if which mpirun >/dev/null; then
  mpirun="mpirun -np 4"
fi

if ! [ -f "${graph}.txt" ]; then
  echo $monda is:history \
    -Om csv --csv_separator ',' --csv_field_enclosure '' "$@"
  $monda is:history \
    -Om csv --csv_separator ',' --csv_field_enclosure '' "$@" >${graph}.txt
fi

if ! [ -f "${outdir}/${graph}.umx" ]; then
  cut -d ',' -f 2- "${graph}.txt"  | tail -n +2 >${graph}_som.txt
  echo $mpirun somoclu -x 100 -y 100 ${graph}_som.txt ${graph}
  $mpirun somoclu -x 100 -y 100 ${graph}_som.txt ${graph}
fi

octave -q <<EOF
m=load("${graph}.umx");
s=size(m,1);
x=(1:s);
y=(1:s);
colorbar();
surface(x,y,m);
print("${graph}-som.png");

EOF

