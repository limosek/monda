#!/bin/sh

set -e

if [ -z "$1" ]; then
    echo "$0 graphname [monda_options]"
    exit 2
fi
graph=$1
shift

if ! [ -f "${graph}.txt" ]; then
  echo $(dirname $0)/monda.php is:history \
    -Om csv --csv_separator ',' --csv_field_enclosure '' "$@"
  $(dirname $0)/monda.php is:history \
    -Om csv --csv_separator ',' --csv_field_enclosure '' "$@" >${graph}.txt
fi

octave -q <<EOF

h=dlmread("${graph}.txt");
sy=size(h,1);
sx=size(h,2)-1;
x=h(2:sy,1);
y=h(2:sy,2:sx);
for i=1:sx-1
  y(:,i)=y(:,i)/norm(y(:,i));
endfor

f=figure();
set(f,'papertype', 'a4');
plot(x,y);
title(sprintf("Item data from %s to %s (%d items)",
        strftime("%Y-%m-%d %H:%M:%S",localtime(min(x))),
        strftime("%Y-%m-%d %H:%M:%S",localtime(max(x))),
        sy));
xlabel(sprintf("t[S] (start %s, end %s)",
        strftime("%Y-%m-%d %H:%M:%S",localtime(min(x))),
        strftime("%Y-%m-%d %H:%M:%S",localtime(max(x)))));
print("${graph}-xy.png");

EOF

