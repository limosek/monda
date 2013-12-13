#!/bin/sh

# This script should be run under any regular user daily
# It will get data from zabbix and analyze it
# It will gzip analyzed .m files
# Monda directory is derived from script name

MONDAHOME=$(dirname "$0")

# Maximum simultanous jobs
JOBS=4

# Host groups to analyze
# hostgroup/outdir/precision
HOSTGROUPS="servers/servers/60 cisco/cisco/300"

renice 19 $$ >/dev/null 2>/dev/null
ionice -c 3 -p $$ >/dev/null 2>/dev/null

if ! [ -r "$MONDAHOME/Makefile" ]; then
  echo "$MONDAHOME/Makefile does not exists?" >&2
  exit 2
fi

cd $MONDAHOME || exit 1
for h in $HOSTGROUPS; do
  hg=$(echo $h| cut -d '/' -f 1)
  od=$(echo $h| cut -d '/' -f 2)
  pr=$(echo $h| cut -d '/' -f 3)
  mkdir -p "$od" || exit 3
  make -k -j$JOBS TIME_START="$(date -d '00:00 yesterday' +@%s)" HOSTGROUP="$hg" OUTDIR="$od" DELAY=$pr
  make gzip -j4 OUTDIR="$od"
done >cron.log 2>&1
