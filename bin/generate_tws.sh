#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 graphname start end [tws_options]"
    exit 2
fi
graph=$1
shift

outdir=$(dirname $0)/../out/${graph}
monda=$(dirname $0)/monda.php
mkdir -p $outdir

html=$outdir/tws.html
cat >$html <<EOF
EOF

start="$1"
end="$2"
shift
shift

gnameh=tws-samehour
echo $monda gm:tws "$@" -s "$start" -e "$end" --corr_type samehour --gm_format svg >&2
if ! $monda gm:tws "$@" -s "$start" -e "$end" --corr_type samehour --gm_format svg >$outdir/$gnameh.svg; then
    rm $outdir/$gname2.svg
fi
gnamed=tws-samedow
echo $monda gm:tws "$@" -s "$start" -e "$end" --corr_type samedow --gm_format svg >&2
if ! $monda gm:tws "$@" -s "$start" -e "$end" --corr_type samedow --gm_format svg >$outdir/$gnamed.svg; then
    rm $outdir/$gname2.svg
fi
echo "<div style='border: 1px solid black'>" >>$html
echo "<object width=\"100%\" id=\"$gname\" data=\"$gnameh.svg\" type=\"image/svg+xml\"></object>" >>$html
echo "<object width=\"100%\" id=\"$gname\" data=\"$gnamed.svg\" type=\"image/svg+xml\"></object>" >>$html
echo "</div>" >>$html

cat >>$html <<EOF
EOF
