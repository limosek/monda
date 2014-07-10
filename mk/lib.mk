
# We need to use bash
SHELL=/bin/bash

# Hack to represent space character. Leave untouched
space :=
space +=

TW=$(PHPDIR)/timewindow.php 

define preprocess/window
PREPROCESS += pp-$(1)-$(INTERVAL)
pp-$(1)-$(INTERVAL):
	$(TW) -f "$(2)" -T "$(INTERVAL)"
endef

# Zabbix sql commandline wrapper
ifeq ($(ZABBIX_DB_TYPE),MYSQL)
 ZSQL = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)'
 ZSQLC = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)' -e
else
 ZSQL = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)'
 ZSQLC = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)' -c
endif

# Monda sql commandline wrapper
ifeq ($(MONDA_DB_TYPE),MYSQL)
 MSQL = mysql '-u$(MONDA_DB_USER)' '-p$(MONDA_DB_PASSWORD)' '-h$(MONDA_DB_SERVER)' '-P$(MONDA_DB_PORT)' -A '-D$(MONDA_DB)'
 MSQLC = mysql '-u$(MONDA_DB_USER)' '-p$(MONDA_DB_PASSWORD)' '-h$(MONDA_DB_SERVER)' '-P$(MONDA_DB_PORT)' -A '-D$(MONDA_DB)' -e
else
 MSQL = PGPASSWORD='$(MONDA_DB_PASSWORD)' psql -U '$(MONDA_DB_USER)' -h '$(MONDA_DB_SERVER)' -p '$(MONDA_DB_PORT)' -d '$(MONDA_DB)'
 MSQLC = PGPASSWORD='$(MONDA_DB_PASSWORD)' psql -U '$(MONDA_DB_USER)' -h '$(MONDA_DB_SERVER)' -p '$(MONDA_DB_PORT)' -d '$(MONDA_DB)' -c
endif

WINDOWS=$(shell $(PHPDIR)/dateintervals.php -F "$(TIME_START)" -T "$(TIME_END)" -S "$(INTERVAL)" -t 300 -D U)

