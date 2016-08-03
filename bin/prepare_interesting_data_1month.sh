#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 site_name"
    exit 2
fi
site="$1"
shift

month=$(date -d '1 month ago' "+%Y-%m")
start=$(date -d "$month-01" "+%Y.%m.%d")
for i in 32 31 30 29 28; do
  if date -d "$month-$i" "+%Y-%m-%d" >/dev/null 2>/dev/null; then
     break
  fi
done

end=$(date -d "$month-$i" "+%Y-%m-%d")

_monda() {
  echo
  echo $(dirname $0)/monda.php "$@" -Om csv -s "$start" -e "$end" --anonymize_key MONDA --anonymize_items --anonymize_hosts --anonymize_urls >&2
  $(dirname $0)/monda.php "$@" -Om csv -s "$start" -e "$end" --anonymize_key MONDA --anonymize_items --anonymize_hosts --anonymize_urls
}

outdir=$(dirname $0)/../out/$site/month
if [ -d $outdir ]; then
    echo "Directory $outdir already exists. Please remove it before run."
    exit 2
fi
mkdir -p $outdir

# Compute item, host and correlations statistics
(
_monda cron:1week --sub_cron_targets
_monda ic:compute --items '@net.if~@system.cpu' -l 1day,1hour
_monda ic:compute --items '@net.if~@system.cpu' --corr_type samedow -l 1day
_monda ic:compute --items '@net.if~@system.cpu' --corr_type samehour -l1hour
_monda is:show >$outdir/is.csv
_monda is:stats >$outdir/iss.csv
_monda hs:show >$outdir/hs.csv
_monda hs:stats >$outdir/hss.csv
_monda ic:show >$outdir/ic.csv
_monda ic:show --corr_type samehour >$outdir/is_hour.csv
_monda ic:show --corr_type samedow >$outdir/is_day.csv
_monda ic:stats >$outdir/ics.csv
_monda gm:tws -s "$start" -e "$end" --corr_type samehour --gm_format svg >$outdir/tws.svg
tws=$($monda tw:show -s "$1" -e "$2" | sort -n | cut -d ' ' -f 1)
for tw in $tws; do
     _monda gm:icw -w $tw --loi_sizefactor 0.0001 --gm_format svg >$outdir/ics-$tw.svg
done
) 2>&1 | tee $outdir/monda.log
