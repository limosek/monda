#!/bin/sh

cd $(dirname $0)

./monda is:show -s "00:00 1 day ago" -Hg '' -Ov expanded >is_day.csv
./monda is:show -s "00:00 1 week ago" -Hg '' -Ov expanded >is_week.csv

./monda ic:show -s "00:00 1 week ago" -Hg '' -Ov expanded >ic_week.csv
./monda ic:show -s "00:00 1 month ago" -Hg '' -Ov expanded >ic_month.csv
