
-include config.mk

ifeq ($(ZABBIX_DB_TYPE),MYSQL)
ZSQL = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)'
ZSQLC = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)' -e
else
ZSQL = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)'
ZSQLC = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)' -c
endif

ifneq ($(V),)
GH=./gethistory.php -e
OCTAVE=octave
else
GH=./gethistory.php
OCTAVE=octave -q
endif

define testtool
	@if ! which $(1) >/dev/null; then echo $(2); exit 2; fi
endef

# Parameter: host start_date interval start_time
define analyze/host/interval
out/$(1)-$(2)-$(3):
	($(GH) -f "$(4)" -T "$(3)" -i '$(PERSERVER)' -H '$(1)'; cat analyze.m) | $(OCTAVE) >out/$(1)-$(2)-$(3) ;
endef

# Parameter: host start_date start_timestamp
define analyze/host
$(1): $(foreach interval,$(INTERVALS),out/$(1)-$(2)-$(interval))
$(foreach interval,$(INTERVALS),$(eval $(call analyze/host/interval,$(1),$(2),$(interval),$(3))))
$(1)-clean:
	rm -f out/$(1)*
endef

ifneq ($(wildcard config.inc.php),)
 ifneq ($(HOSTGROUP),)
HOSTS=$(shell ./gethostsingroup.php $(HOSTGROUP))
 endif
endif

DATE_START=$(shell date -d "$(TIME_START)" +%Y_%m_%d_%H00)

$(foreach host,$(HOSTS),$(eval $(call analyze/host,$(host),$(DATE_START),$(TIME_START))))

all: _test analyze $(foreach host,$(HOSTS),$(host))

_test: _testconf _testtools _config.inc.php

_testconf:
ifeq ($(ZABBIX_USER),)
	@echo "\nYou must define all variables in config.mk before use!\nYou can use 'cp config.mk.dist config.mk' for start but edit your values to fit your setup!\n"
	@exit 2
endif
ifeq ($(ZABBIX_USER),somewebuser)
	@echo "\nYou cannot use distribution config file! Edit config.mk first!\n"
endif

_testtools:
	$(call testtool,octave,Install GNU Octave first and do not forget signaling toolkit!)
	$(call testtool,php,Install PHP first!)

_config.inc.php config:
	@echo "<?php \
	define('ZABBIX_USER','$(ZABBIX_USER)'); \
	define('ZABBIX_PW','$(ZABBIX_PW)'); \
	define('ZABBIX_URL','$(ZABBIX_URL)'); \
	define('ZABBIX_DB_TYPE',''); \
	define('ZABBIX_DB_SERVER','$(ZABBIX_DB_SERVER)'); \
	define('ZABBIX_DB_PORT',$(ZABBIX_DB_PORT)); \
	define('ZABBIX_DB','$(ZABBIX_DB)'); \
	define('ZABBIX_DB_USER','$(ZABBIX_DB_USER)'); \
	define('ZABBIX_DB_PASSWORD','$(ZABBIX_DB_PASSWORD)'); \
	" >config.inc.php
	@mkdir -p out

info:
	@echo Hosts: $(foreach host,$(HOSTS),$(host))
	@echo Start: $(START_DATE)
	@echo Intervals: $(INTERVALS)
	
clean:	$(foreach host,$(HOSTS),$(host)-clean)
	rm -f config.inc.php *.out
	$(MAKE) config
	
analyze: _test $(foreach host,$(HOSTS),$(host))
	
patchdb:
	$(ZSQL) <sql_triggers_backuptables.sql

reorderdb:
	$(ZSQLC) 'alter table history_uint_backup order by clock,itemid'
	$(ZSQLC) 'alter table history_backup order by clock,itemid'
	$(ZSQLC) 'alter table trends_uint_backup order by clock,itemid'
	$(ZSQLC) 'alter table trends_backup order by clock,itemid'
	