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

### Cloning

There is no release yet. You must use git to clone monda repository. 

```
# apt-get install git
# su -l monda
$ git clone https://code.google.com/p/monda/
```

