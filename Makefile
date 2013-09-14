
PERSERVER=net.if.in\[eth0,bytes\]|net.if.out\[eth0,bytes\]|system.cpu.load|proc.num\[,,run\]|vfs.fs.size\[/,free\]|vfs.fs.size\[\/,used\]
H1=-24hour
H2=-8hour
H3=-4hour

-include config.mk

all: _test _config.inc.php $(HOSTS)

_test:
ifeq ($(ZABBIX_USER),)
	@echo "You must define all variables in config.mk before use! You can use config.mk.dist for start but edit your values to fit your setup!"
	exit 2
endif

_config.inc.php:
	@echo "<? \
	define('ZABBIX_USER','$(ZABBIX_USER)'); \
	define('ZABBIX_PW','$(ZABBIX_PW)'); \
	define('ZABBIX_URL','$(ZABBIX_URL)'); \
	define('ZABBIX_DB_TYPE',''); \
	define('ZABBIX_DB_SERVER','$(ZABBIX_DB_SERVER)'); \
	define('ZABBIX_DB_PORT',$(ZABBIX_DB_PORT)); \
	define('ZABBIX_DB','$(ZABBIX_DB)'); \
	define('ZABBIX_DB_USER','$(ZABBIX_DB_USER)'); \
	define('ZABBIX_DB_PASSWORD','$(ZABBIX_DB_PASSWORD)'); \
	"
clean:
	rm -f *.hin *.tin *.out *.tdesc.m *.hdesc.m

%.hin:
	export host=$(shell basename $@ .hin); ./gethistory.php -B -H$$host -i'$(PERSERVER)' -h$(H) -a$(ACCU) >$$host.hin || rm -f $@

%.tin:
	host=$(shell basename $@ .tin); ./gettrends.php -e -B -H$$host -i '$(PERSERVER)' -h$(T) >$$host.tin || rm -f $@

donalisa.out: donalisa.tin
	./corel.m

reorder:
	$(ZSQL) -e 'alter table history_uint_backup order by clock,itemid'
	$(ZSQL) -e 'alter table history_backup order by clock,itemid'
	$(ZSQL) -e 'alter table trends_uint_backup order by clock,itemid'
	$(ZSQL) -e 'alter table trends_backup order by clock,itemid'
	