# Monitoring system data analysis

This package contains tools to analyze data from monitoring system.
At this moment, only Zabbix is supported. Please, read documentation before 
using this package. Monda can be performance killer for your Zabbix installation!

## Basics

Monda will use lot of resources and can be performance killer for Zabbix! 
There will be lot of SQL queries which can cause big load or big IOwait time.
Even if Monda is made "as safe as possible", it is really hard to predict what
it would do with your Zabbix server. 

## Two databases concept

Monda is developed with dual database concept. One is native Zabbix database,
used by Zabbix server, second is Monda database. In theory, both of them can be 
one database system, but main concept of Monda is, to analyze data in idle
server time and not to slowdown any Zabbix process. From this reason, Monda uses
its own database system for storing its data.

## More zabbixes in one Monda database

It is possible to analyse more zabbix servers using one monda database. You can use zabbix_alias or zabbix_id
for this. All statistics, correlation and other facts about zabbix servers will be in one monda database.

### Monda host and user considerations

Monda itself can run on same server a Zabbix server. Best practice is to run as **monda** user.
In fact, it can be any user different from root. If you do not want to touch your Zabbix server 
and want to use dedicated server for monda, it is possible. But you have to permit monda to access zabbix database 
and zabbix api.

### Zabbix database considerations

Zabbix database can be in theory mysql or postgresql. If you want to analyze lots of data in history,
take care about table partitioning. Especialy history and history_uint table. There will be lot
of queries to this tables.

### Monda database considerations

Monda database has to be postgresql now. It is good idea to be on another server
than Zabbix. But if you are lazy, you can use same server as Zabbix. Depending on
your Zabbix setup, Monda database will grow significantly!

## Installing

Before installing, please read text above and choose right databases and host(s). We will need 
two database configs. One will become from Zabbix setup and will be referrenced as 
**ZabbixDB**. Second will be Monda db and will be referrenced as **MondaDB**. Next, we
will need host and user for monda process itself. See above.

### Zabbix preparation

Create monda user using Zabbix frontend. This user should have readonly rights for all 
hosts which you want to process by Monda. User must have API access enabled.
Next to this, create host group **monda** (name is case sensitive!).
All hosts in this group will be processed by Monda. Or you can use another group and use **-Hg** 
parameter.

### Cloning

There is no release yet. You must use git to clone monda repository. Git must be installed before.

```
# su -l monda
git clone git@github.com:limosek/monda.git
cd monda
. env.sh
```
Optionaly, you can add PATH for monda account permanently:

```
cat >>~/.profile <<EOF
  export PATH=\$PATH:~/monda/
EOF
```

### Installing DB

First you have to create MondaDB. According to your setup, you have to feed sql/init.sql into your 
SQL command. You have to be postgresql admin user to run scripts. There are three scripts.
- init_db.sql to create role and DB It will use default password for role monda!
- init_schema.sql to create tables and objects inside monda DB
- drop.sql to drop database, tables and roles (if you want to start from scratch again)
- db.sh {init|drop} will try to do all

```
cd ~/monda
su postgres
./misc/db.sh init
```

### Configuring

Monda configuration is based on commandline arguments, INI file and environment variables. 

All commandline arguments can be saved into .mondarc file. This is located at ~/.mondarc. It can contain arguments 
like passed from commandline (but only long variant). All lines starting with **#** will be ignored.
If you will pass all informations on commandline, you do not need config.

Example config file:
```
cat ~/.mondarc

[global]
# Zabbix API url, user and password
zabbix_api_url='http://zabbix/api_jsonrpc.php'
zabbix_api_user=monda
zabbix_api_pw=someapipassword
# Zabbix api enable (default disabled)
zabbix_api=1

# ZabbixDb DSN
zabbix_dsn='pgsql:host=127.0.0.1;port=5432;dbname=zabbix'
zabbix_db_user=zabbix
zabbix_db_pw=some_password
# Zabbix ID (there can be more zabbix server in one monda db). If unspecified, "1" is used.
zabbix_id=1

# MondaDb DSN
monda_dsn='pgsql:host=127.0.0.1;port=5432;dbname=monda'
monda_db_user=monda
monda_db_pw=some_password

# This section will be automaticaly read, if monda is used with --zabbix_alias site1
# or if environment variable MONDA_ZABBIX_ALIAS=site1
[zabbix-site1]
zabbix_dsn='pgsql:host=some.other.host;port=5432;dbname=zabbix'
zabbix_db_user=zabbix
zabbix_db_pw=some_other_password
# Zabbix ID (has to be unique per alias!)
zabbix_id=2

# Module specific parameters
[Tw]
#output_mode=cli

# Module:action specific parameters
[Is:compute]
#
max_windows_per_query=5

```
Basic help and list of modules can be obtained by runing:
```
monda [module]

```

Advanced help can be obtained by
```
monda module -xh 2>&1 |less
```

## Running

First we will create timewindows to inspect. This will test MondaDb connection.
```
monda tw:create -s yesterday
```

Next, we will try to compute item statistics for created windows. This will test ZabbixDB
connection
```
monda is:compute -s yesterday
monda is:show -s yesterday
```

You can use cron module, which will do all work automaticaly. **-Sc** parameter 
will do cron subtargets too (eg. all days from week). Take care! This can be very 
expensive to do cron with subtargets for long time (like last example).
```
monda cron:1hour
monda cron:1day -Sc
monda cron:1week -Sc
```

To see text results, use
```
monda tw:show -s yesterday -Om csv
monda is:show -s yesterday -Om csv -Ov expanded
monda hs:show -s yesterday
monda ic:show -s yesterday -Om csv -Ov expanded
```

## Cache
Monda uses internal caching system. Cache is located in temp/cache directory. Monda tries to cache all
operations which needs lot of CPU, network or time. For example, all Zabbix API calls are cached by default. 
Same to zabbix queries which operates over history. You can set default cache timeouts by *--api_cache_expire* and 
*--sql_cache_expire* options. It is possible even to turn off caching by *--nocache* option but it is not recomended.
If you want to cleanup cache, simply use *./bin/cache_clean.sh*. 

## Fine-tune your parameters
It is hard to decide what to analyze without more informations about your setup. You can fine-tune process.
Monda tries to find most interresting items, host and windows automaticaly. But you can change it by parameters.
Select explicitly, which items to correlate
```
monda ic:compute --items '@net.if' #will correlate network statictics. @ means regular expression on item key
monda ic:compute --items '@net.if~@system.cpu' #will correlate network statictics and system statistics. ~ means more items
```

Find correlations of same items in same hour day
```
monda ic:compute --corr_type samehour
```

Find correlations of same items in same day of week
```
monda ic:compute --corr_type samedow
```

Select explicitly hosts
```
monda ic:compute --hosts server1,server2,server3 
```

Find best window ids
```
monda tw:show 
```

Find best items
```
monda is:show
```

Find best correlations
```
monda ic:show
```

Sometime we want to omit correlation of 1 (similar items which correlate by default)
```
monda ic:show --max_corr 0.95
```

## Graphical outputs

Monda uses Graphviz to show some results. You must install graphviz. If you open svg files in browser and you
defined zabbix url in your monda config, objects are clickable and follow you to your zabbix site and history for selected 
items or hosts so you can for example look into zabbix graphs how correlation looks.

Generate time graph with correlations
```
monda gm:tws -s "1 month ago" --loi_sizefactor 0.001 --corr_type samedow --gm_format svg >tws.svg
```

Generate item correlation graph in one window with id wid.
```
monda gm:ics -w wid -Ov expanded --loi_sizefactor 0.001 --gm_format svg  >ics.svg
```

## Outputs for processing by external software

Generate data for weka
```
monda is:history -w wid -Om arff >history.arff
```

Add trigger values into history for external processing. You have to pass triggerids to add
```
monda is:history -w wid --triggerids_history 10,20,30 -Om arff >history.arff
```

## Sample results

### Timewindows with their correlations
![TW dependency](/example-outputs/Tw_correlations.png?raw=true)

### Items and their correlations inside timewindow
![Item dependency 1](/example-outputs/Item_correlations_inside_timewindow1.png?raw=true)
![Item dependency 2](/example-outputs/Item_correlations_inside_timewindow2.png?raw=true)

You can find some more results [here](https://box.slu.cz/index.php/s/VkjZDPWZZ7Fmy7f).
Please note that data are anonymized so there are only itemids and urls are not working.
You are welcomed to upload your anonymized results (see below).

## Exporting and anonymizing data
It would be nice to share community data and analysis output to some central repository to make monda better.
But there are security cosiderations. You probably do not want to share internal data from your monitoring server.
Monda can anonymize data. Please take care, this is not carrefuly tested! There can be still some data not-anonymized. 
Please take care before submiting. You can create your own site directory in our [owncloud server](https://box.slu.cz/index.php/s/SqOp5bybVup6lzq)
and submit interresting results. Use *--anonymize_key* , *--anonymize_hosts*, *--anonymize_items*, *--anonymize_urls* directives.
Anonymization is not reversible. It uses sha1 algorithm with combination of key.

There are three scripts prepared for exporting your interresting site data anonymized. You are welcomed to upload results!
This scripts will process last day, last week or last month of data into out/site_name/{day|month|week}. Please take care, this can really utilize 
your zabbix database. Longer interval means long time, CPU and disk IO! 

Export data from yesterday midnight to today midnight (last day).
```
./bin/prepare_interesting_data_1day.sh site_name 
```

Export data from week before last monday.
```
./bin/prepare_interesting_data_1week.sh site_name 
```

Export data for previous month. 
```
./bin/prepare_interesting_data_1month.sh site_name 
```

After it, you can upload it to our [owncloud server](https://box.slu.cz/index.php/s/SqOp5bybVup6lzq)

### Files inside export

#### is.csv
Item statistics in timewindow

- itemid - itemid from zabbix
- hostid - hostid which itemid belongs to
- min_,max_,avg_,stddev_ - minimum, maximum, average and standard deviation for given item in time window
- cv - [Coefficient of Variation](https://en.wikipedia.org/wiki/Coefficient_of_variation) 
- cnt - count of data in history for given item in timewindow
- windowid - timewindow id
- loi - Level of interrest. Bigger value mean more important item

#### iss.csv
Item statistics aggregated for timewindows

- itemid - itemid from zabbix
- wcnt - count of timewindows with this itemid
- loi - average loi for given itemid
- loiw - loi*wcnt
- min_,max_,avg_,stddev_ - minimum, maximum, average and standard deviation for given item in time windows
- cnt - average of data count
- cv - average of [Coefficient of Variation](https://en.wikipedia.org/wiki/Coefficient_of_variation) 

#### hs.csv
Host statistics in timewindow

- hostid - hostid which itemid belongs to
- windowid - timewindow id
- cnt - Total count of data history for host
- items - Total count of item count in window
- loi - Level of interrest

#### hss.csv
Host statistics aggrefated for timewindows

- hostid - hostid which itemid belongs to
- cnt - Average count of data history for host
- items - Average count of item count in window
- loi - Average level of interrest

#### ic.csv
Item correlation statistics per window

- windowid1,windowid2 - timewindows where correlation found
- itemid1,itemid2 - itemids which correlated
- corr - correlation from (-1 to 1)
- cnt - number of data in history used for correlation
- icloi - Level of interrest

#### ic_dow.csv
Item correlation statistics between windows with same day of week.

#### ic_hod.csv
Item correlation statistics between windows with same hour of day.
