# Monitoring system data analysis

This package contains tools to analyze data from monitoring system.
At this moment, only Zabbix is supported. Please, read documentation before 
using this package. Monda can be performance killer for your Zabbix installation!

## Basics

Monda will use lot of resources and can be performance killer for Zabbix! 
There will be lot of SQL queries which can cause big load or big IOwait time.
Even if Monda is made "as safe as possible", it is really hard to predict what
it would do with your Zabbix server. Monda will try to use all possible techniques 
to detect, if it is possible to place next job or wait for lower server load.
Take into account that there will be three sources of load:

- Monda process itself (will be automaticaly niced)
- Zabbix database server (queries will be niced if there is prioritize extension in postgresql). See [http://pgxn.org/dist/prioritize/]
- Monda database server (queries will be niced if there is prioritize extension in postgresql). See [http://pgxn.org/dist/prioritize/]

## Two databases concept

Monda is developed with dual database concept. One is native Zabbix database,
used by Zabbix server, second is Monda database. In theory, both of them can be 
one database system, but main concept of Monda is, to analyze data in idle
server time and not to slowdown any Zabbix process. From this reason, Monda uses
its own database system for storing its data.

### Monda host and user considerations

Monda itself can run on same server a Zabbix server. Best practice is to run as **monda** user.
In fact, it can be any user different from root. If you do not want to touch your Zabbix server 
and want to use dedicated server for monda, it is probably good to have Monda DB and Monda process
on same machine.

### Zabbix database considerations

Zabbix database can be in theory mysql or postgresql, but only postgresl was 
tested up to now. If you want to analyze lots of data in history, take care about
table partitioning. Especialy history and history_uint table. There will be lot
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

Next to this, create monda user using Zabbix frontend. This user should have readonly rights for all 
hosts which you want to process by Monda. Next to this, create host group Monda. All hosts in this group
will be processed by Monda.

### Cloning

There is no release yet. You must use git to clone monda repository. Git must be installed before.

```
# su -l monda
$ git clone https://code.google.com/p/monda/
$ cd monda
$ export PATH=$PATH:$PWD/bin
```
Optionaly, you can add PATH for monda account permanently:

```
$ cat >>~/.profile <<EOF
  export PATH=\$PATH:~/monda/
EOF
```


### Installing DB

First you have to create MondaDB. According to your setup, you have to feed sql/init.sql into your 
SQL command. You have to be postgresql admin user to run scripts. There are three scripts.
- init_db.sql to create role and DB
- init_schema.sql to create tables and objects inside monda DB
- drop.sql to drop database, tables and roles (if you want to start from scratch again)

```
# cd /home/monda/monda
# su postgres
$ psql <sql/init_db.sql
$ psql monda <sql/init_schema.sql
```

### Configuring

Monda configuration is based entirely on commandline arguments. 

All commandline arguments can be saved into .mondarc file. This is located at ~/.mondarc. It can contain arguments 
like passed from commandline. All lines which do not start with '-' are ignored as comment. 
If you will pass all informations on commandline, you do not need config.
Example config file with minimalistic configuration: 
```
$ cat ~/.mondarc

# Zabbix API url, user and password
--zabbix_api_url 'http://zabbix/api_jsonrpc.php'
--zabbix_api_user monda
--zabbix_api_pw somepassword
# Zabbix api enable (default disabled)
--za

# ZabbixDb DSN
--zabbix_dsn 'pgsql:host=127.0.0.1;port=5432;dbname=zabbix'
--zabbix_db_user zabbix
--zabbix_db_pw some_password
# Zabbix ID (there can be more zabbix server in one monda db)
--zabbix_id 1

# MondaDb DSN
--monda_dsn 'pgsql:host=127.0.0.1;port=5432;dbname=monda'
--monda_db_user monda
--monda_db_pw some_password

```
Basic help and list of modules can be obtained by runing:
```
$ monda [module]

```

Advanced help can be obtained by
```
$ monda [module] -xh 2>&1 |less
```

