
# Monda Makefile
-include config.mk
ifneq ($(C),)
include config-$(C).mk
endif
include lib.mk

all: _test
	@$(MAKE) analyze 

get: $(foreach host,$(HOSTS),get-$(host))

analyze: $(foreach host,$(HOSTS),analyze-$(host))

graphs: $(foreach host,$(HOSTS),graphs-$(host))

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
	define('ZABBIX_DB_TYPE','$(ZABBIX_DB_TYPE)'); \
	define('ZABBIX_DB_SERVER','$(ZABBIX_DB_SERVER)'); \
	define('ZABBIX_DB_PORT',$(ZABBIX_DB_PORT)); \
	define('ZABBIX_DB','$(ZABBIX_DB)'); \
	define('ZABBIX_DB_USER','$(ZABBIX_DB_USER)'); \
	define('ZABBIX_DB_PASSWORD','$(ZABBIX_DB_PASSWORD)'); \
	" >config.inc.php
	@mkdir -p out

info: info1 info2 info3
info1:
	@echo Hosts: $(foreach host,$(HOSTS),$(host))
	@echo TIME_START=$(TIME_START)
	@echo TIME_TO=$(TIME_TO)
	@echo TIME_STEP=$(TIME_STEP)
	@echo Starts: $(START_DATES_NICE)
	@echo Intervals: $(INTERVALS)
	@echo Out directory: $(O)
	@echo Targets:

info2:	$(foreach host,$(HOSTS),info-$(host))

info3:
	@echo Done

clean:	$(foreach host,$(HOSTS),clean-$(host)) tmpclean
	rm -f config.inc.php *.out
	$(MAKE) config
	
distclean: tmpclean
	rm -rf $(O)/*

tmpclean:
	rm -f $(O)/*tmp $(O)/*log

%.az:
	$(call analyze/octave,$@,$@.tmp) && mv $@.tmp $@

%.az:	%.m
	$(call analyze/octave,$<,$@)

%.az:	%.m.gz
	gunzip $<
	$(call analyze/octave,$(shell dirname $<)/$(shell basename $< .gz),$@)

%.m.gz:	%.m
	$(GZIP) $<

%.m-gz:	%.m.gz
	@$(GUNZIP) $<
	
%.m.log.gz: %.m.log
	@$(GZIP) $<

%.az.log.gz: %.az.log
	@$(GZIP) $<
	
gzip: $(shell find $(O) -name '*.m' | sed -e s/\.m\$$/\.m\.gz/) $(shell find $(O) -name '*.m.log' | sed -e s/\.m\.log\$$/\.m\.log\.gz/) $(shell find $(O) -name '*.az.log' | sed -e s/\.az\.log\$$/\.az\.log\.gz/)
	@echo $^

gunzip: $(shell find $(O) -name '*.m.gz' | sed -e s/\.m\.gz\$$/\.m\-gz/)
	@echo $^
	
analyzem: $(shell find $(O) -name '*.m' | sed -e s/\.m\$$/\.az/)
	@echo $^
	
patchdb:
	$(ZSQL) <sql_triggers_backuptables.sql

reorderdb:
	$(ZSQLC) 'alter table history_uint_backup order by clock,itemid'
	$(ZSQLC) 'alter table history_backup order by clock,itemid'
	$(ZSQLC) 'alter table trends_uint_backup order by clock,itemid'
	$(ZSQLC) 'alter table trends_backup order by clock,itemid'

query:
	$(ZSQLC) '$(Q)'

# Create all targets
$(foreach host,$(HOSTS),$(eval $(call analyze/host,$(host))))
