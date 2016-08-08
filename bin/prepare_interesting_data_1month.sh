#!/bin/sh

if [ -z "$1" ]; then
    echo "$0 site_name [noanon]"
    echo "Data are anonymized by default."
    echo "If you do not want to anonymise, use noanon parameter."
    echo "No data will be sent. Only local output."
    echo 
    exit 2
fi
site="$1"
shift

if [ -z "$1" ]; then
  anonymize="--anonymize_key MONDA --anonymize_items --anonymize_hosts --anonymize_urls"
else 
  export expanded="--output_verbosity=expanded"
  shift
fi

_monda() {
  echo >&2
  echo $(dirname $0)/monda.php "$@" $anonymize >&2
  $(dirname $0)/monda.php "$@" $anonymize
}

month=$(date -d '1 month ago' "+%Y-%m")
start=$(date -d "$month-01" "+%Y.%m.%d")
for i in 32 31 30 29 28; do
  if date -d "$month-$i" "+%Y-%m-%d" >/dev/null 2>/dev/null; then
     break
  fi
done

end=$(date -d "$month-$i" "+%Y-%m-%d")

outdir=$(dirname $0)/../out/$site/month
if [ -d $outdir ]; then
    echo "Directory $outdir already exists. Please remove it before run."
    exit 2
fi
mkdir -p $outdir

# Compute item, host and correlations statistics
(
_monda cron:1month -s "$start" -e "$end" 
for tw in $tws; do
 _monda ic:compute -w $tw
done
tws=$(_monda tw:show -Om brief --brief_columns id)  
_monda is:show -s "$start" -e "$end"  --output_mode csv $expanded >$outdir/is.csv
_monda is:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/iss.csv
_monda hs:show -s "$start" -e "$end"  --output_mode csv $expanded >$outdir/hs.csv
_monda hs:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/hss.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv $expanded >$outdir/ic.csv
_monda ic:show -s "$start" -e "$end"  --ic_notsamehost --output_mode csv $expanded >$outdir/ich.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv $expanded --corr_type samehour >$outdir/ic_hod.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv $expanded --corr_type samedow >$outdir/ic_dow.csv
_monda ic:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/ics.csv
if ! _monda gm:tws -s "$start" -e "$end" $expanded --corr_type samehour --gm_format svg >$outdir/tws.svg; then
    rm -f $outdir/tws.svg
fi
for tw in $tws; do
     if ! _monda gm:icw -w $tw $expanded --loi_sizefactor 0.0001 --gm_format svg >$outdir/ics-$tw.svg; then
        rm -f $outdir/ics-$tw.svg
     fi
done
) 2>&1 | tee $outdir/monda.log
