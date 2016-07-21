#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 graphname [monda_options]"
    exit 2
fi
graph=$1
shift

outdir=$(dirname $0)/../out
monda=$(dirname $0)/monda.php

tws=$($monda tw:show $* | cut -d ' ' -f 1)
html=$outdir/icw-${graph}.html
cat >$html <<EOF
EOF

for tw in $tws; do
     gname=icw-${graph}-$tw
     if ! $monda gm:icw $* -w $tw --gm_format svg >$outdir/$gname.svg; then
        rm $outdir/$gname.svg
     fi
     echo "<div style='border: 1px solid black'>" >>$html
     echo "<object width=\"100%\" id=\"$gname\" data=\"$gname.svg\" type=\"image/svg+xml\"></object>" >>$html
     echo "</div>" >>$html
done

cat >>$html <<EOF
EOF
