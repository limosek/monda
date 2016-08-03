#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 site_name"
    exit 2
fi
site="$1"
shift

start="last monday -7 day"
end="last monday"

_monda() {
  echo >&2
  echo $(dirname $0)/monda.php "$@" --anonymize_key MONDA --anonymize_items --anonymize_hosts --anonymize_urls >&2
  $(dirname $0)/monda.php "$@" --anonymize_key MONDA --anonymize_items --anonymize_hosts --anonymize_urls
}

outdir=$(dirname $0)/../out/$site/week
if [ -d $outdir ]; then
    echo "Directory $outdir already exists. Please remove it before run."
    exit 2
fi
mkdir -p $outdir

# Compute item, host and correlations statistics
(
tws=$(_monda tw:show -Om brief --brief_columns id)
_monda cron:1week -s "$start" -e "$end" 
_monda is:show -s "$start" -e "$end"  >$outdir/is.csv
_monda is:stats -s "$start" -e "$end" >$outdir/iss.csv
_monda hs:show -s "$start" -e "$end"  >$outdir/hs.csv
_monda hs:stats -s "$start" -e "$end" >$outdir/hss.csv
_monda ic:show -s "$start" -e "$end"  >$outdir/ic.csv
_monda ic:show -s "$start" -e "$end"  --corr_type samehour >$outdir/ic_hod.csv
_monda ic:show -s "$start" -e "$end"  --corr_type samedow >$outdir/ic_dow.csv
_monda ic:stats -s "$start" -e "$end" >$outdir/ics.csv
_monda gm:tws -s "$start" -e "$end" --corr_type samehour --gm_format svg >$outdir/tws.svg
for tw in $tws; do
     if ! _monda gm:icw -w $tw --loi_sizefactor 0.0001 --gm_format svg >$outdir/ics-$tw.svg; then
        rm -f $outdir/ics-$tw.svg
     fi
done
) 2>&1 | tee $outdir/monda.log
