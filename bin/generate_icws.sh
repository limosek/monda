#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 graphname start end [icw_options]"
    exit 2
fi
graph=$1
shift

outdir=$(dirname $0)/../out/$graph
monda=$(dirname $0)/monda.php
mkdir -p $outdir

tws=$($monda tw:show -s "$1" -e "$2" | cut -d ' ' -f 1)
html=$outdir/icws.html
cat >$html <<EOF
EOF

shift
shift

for tw in $tws; do
     echo "Window $tw" >&2
     gname=icw-$tw
     if ! $monda gm:icw -w $tw "$@" --loi_sizefactor 0.0001 --gm_format svg >$outdir/$gname.svg; then
        rm $outdir/$gname.svg
     fi
     echo "<div style='border: 1px solid black'>" >>$html
     echo "<object width=\"100%\" id=\"$gname\" data=\"$gname.svg\" type=\"image/svg+xml\"></object>" >>$html
     echo "</div>" >>$html
done

cat >>$html <<EOF
EOF
