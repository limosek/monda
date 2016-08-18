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

_topids() {
  local ids
  ids=$(_monda "$2" --output_mode brief --brief_columns "$1" "$3" "$4" "$5" "$6" "$7" "$8" "$9")
  echo $ids | tr ' ' ','
}

_mhistory() {
  _monda "$1" --output_mode csv  "$3" "$4" "$5" "$6" "$7" "$8" "$9" >"$2".csv
  _monda "$1" --output_mode arff "$3" "$4" "$5" "$6" "$7" "$8" "$9" >"$2".arff
}

start="yesterday"
end="today"

outdir=$(dirname $0)/../out/$site/day
if [ -d $outdir ]; then
    echo "Directory $outdir already exists. Please remove it before run."
    exit 2
fi
mkdir -p $outdir

# Compute item, host and correlations statistics
(
_monda cron:1day -s "$start" -e "$end"
export tw10=$(_topids id tw:show --tw_max_rows 10)
export is10=$(_topids itemid is:stats --is_max_rows 10)
export hs10=$(_topids hostid hs:stats --hs_max_rows 10)
_monda is:show -s "$start" -e "$end" --output_mode csv $expanded >$outdir/is.csv
_monda is:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/iss.csv
_monda hs:show -s "$start" -e "$end" --output_mode csv $expanded >$outdir/hs.csv
_monda hs:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/hss.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv $expanded >$outdir/ic.csv
_monda ic:show -s "$start" -e "$end"  --ic_notsamehost --output_mode csv $expanded >$outdir/ich.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv --corr_type samehour $expanded >$outdir/ic_hod.csv
_monda ic:show -s "$start" -e "$end"  --output_mode csv --corr_type samedow $expanded >$outdir/ic_dow.csv
_monda ic:stats -s "$start" -e "$end" --output_mode csv $expanded >$outdir/ics.csv
_monda ec:show -s "$start" -e "$end" --output_mode csv $expanded >$outdir/events.csv
_monda ec:show -s "$start" -e "$end" --output_mode csv $expanded >$outdir/events.csv
_monda is:history -s "$start" -e "$end" -l 1hour --itemids "$is10" -Om csv --similar_corr 0.5 --similar_count 10 >$outdir/is_hist.csv
_mhistory is:history $outdir/is10_hist -s "$start" -e "$end" -l 1hour --itemids "$is10" --similar_corr 0.5 --similar_count 10
_mhistory ic:history $outdir/ic10_hist -s "$start" -e "$end" -l 1hour --itemids "$is10" --similar_corr 0.5 --similar_count 10
_mhistory is:history $outdir/hss10_hist -s "$start" -e "$end" -l 1hour --items '@net.if~@system.cpu.util~@IfIn~@IfOut' --hostids "$hs10" --similar_corr 0.5 --similar_count 10
_mhistory ic:history $outdir/hsc10_hist -s "$start" -e "$end" -l 1hour --items '@net.if~@system.cpu.util~@IfIn~@IfOut' --hostids "$hs10" --similar_corr 0.5 --similar_count 10
(
echo "Top 10 timewindows (1 day):"
_monda tw:show --tw_max_rows 10 -s "$start" -e "$end" -l 1day --output_mode brief --brief_columns id
echo "Top 10 timewindows (1 hour):"
_monda tw:show --tw_max_rows 10 -s "$start" -e "$end" -l 1hour --output_mode brief --brief_columns id
echo "Top 10 items:"
_monda is:stats --is_max_rows 10 -s "$start" -e "$end" --output_mode brief --brief_columns itemid
echo "Top 10 hosts:"
_monda hs:stats --hs_max_rows 10 -s "$start" -e "$end" --output_mode brief --brief_columns hostid
echo "Top 10 correlations:"
_monda ic:show --ic_max_rows 10 -s "$start" -e "$end" --output_mode brief --brief_columns windowid1,itemid1,windowid2,itemid2
) >$outdir/top10.txt
if ! _monda gm:tws -s "$start" -e "$end" $expanded --corr_type samehour --gm_format svg >$outdir/tws.svg; then
    rm -f $outdir/tws.svg
fi
for tw in $tws; do
     if ! _monda gm:icw -w $tw --loi_sizefactor 0.0001 $expanded  --gm_format svg >$outdir/ics-$tw.svg; then
        rm -f $outdir/ics-$tw.svg
     fi
done
) 2>&1 | tee $outdir/monda.log