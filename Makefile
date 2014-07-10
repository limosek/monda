
# Monda Makefile
# Define basics

MKDIR=./mk
PHPDIR=./php
MDIR=./m
SHDIR=./sh
CFGDIR=./cfg

-include $(CFGDIR)/config.mk
ifneq ($(C),)
include $(CFGDIR)/config-$(C).mk
endif
include $(MKDIR)/lib.mk

all: _test
	@$(MAKE) analyze 

_test: _testconf _config.inc.php

_testconf:
ifeq ($(ZABBIX_USER),)
	@echo "\nYou must define all variables in config.mk before use!\nYou can use 'cp config.mk.dist config.mk' for start but edit your values to fit your setup!\n"
	@exit 2
endif
ifeq ($(ZABBIX_USER),somewebuser)
	@echo "\nYou cannot use distribution config file! Edit config.mk first!\n"
endif

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
	define('MONDA_DB_TYPE','$(MONDA_DB_TYPE)'); \
	define('MONDA_DB_SERVER','$(MONDA_DB_SERVER)'); \
	define('MONDA_DB_PORT',$(MONDA_DB_PORT)); \
	define('MONDA_DB','$(MONDA_DB)'); \
	define('MONDA_DB_USER','$(MONDA_DB_USER)'); \
	define('MONDA_DB_PASSWORD','$(MONDA_DB_PASSWORD)'); \
	define('MIN_CORR', $(MIN_CORRELATION)'); \
	define('MIN_VALUES', $(MIN_VALUES)'); \
	define('TIME_PRECISION',$(TIME_PRECISION)'); \
	define('TIMEWINDOW_STEP',$(TIMEWINDOW_STEP)'); \
	" >$(PHPDIR)/config.inc.php

analyze: preprocess
	
clean-itemstat:
	echo "DELETE FROM itemstat;" | $(MSQL) 

clean-itemcorr:
	echo "DELETE FROM itemcorr;" | $(MSQL) 

clean-timewindow: clean-itemstat
	echo "DELETE FROM timewindow;" | $(MSQL) 

clean-windowcorr: clean-itemcorr
	echo "DELETE FROM windowcorr;" | $(MSQL) 
	
clean: clean-itemstat clean-timewindow clean-itemcorr clean-windowcorr
	
	
# Create targets
$(foreach w,$(WINDOWS),$(eval $(call preprocess/window,$(shell echo $(w)|tr ' :' '__'),$(w))))

preprocess: $(PREPROCESS)


