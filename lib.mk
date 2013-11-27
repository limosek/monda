
SHELL=/bin/bash

# Zabbix sql commandline wrapper
ifeq ($(ZABBIX_DB_TYPE),MYSQL)
 ZSQL = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)'
 ZSQLC = mysql '-u$(ZABBIX_DB_USER)' '-p$(ZABBIX_DB_PASSWORD)' '-h$(ZABBIX_DB_SERVER)' '-P$(ZABBIX_DB_PORT)' -A '-D$(ZABBIX_DB)' -e
else
 ZSQL = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)'
 ZSQLC = PGPASSWORD='$(ZABBIX_DB_PASSWORD)' psql -U '$(ZABBIX_DB_USER)' -h '$(ZABBIX_DB_SERVER)' -p '$(ZABBIX_DB_PORT)' -d '$(ZABBIX_DB)' -c
endif

# Verbose
ifneq ($(V),)
 GH=./gethistory.php -e
 OCTAVE=octave --no-window-system --norc
 GZIP=gzip -f
 GUNZIP=gunzip -df
else
 GH=@./gethistory.php
 OCTAVE=octave -q --no-window-system --norc
 GZIP=gzip -f
 GUNZIP=gunzip -df
endif

ifeq ($(V),)
 define analyze/octave
  $(OCTAVE) analyze.m "$(1)" "$(2)" $(TIME_PRECISION) 1>"$(2).log" 2> >(tee -a "$(2).log" >&2)
 endef
else
 define analyze/octave
  @echo "Analyzing $(1)>$(2)";
  $(OCTAVE) analyze.m "$(1)" "$(2)" $(TIME_PRECISION) 2>&1 | tee "$(2).log"
 endef
endif

define analyze/octave/graphs
      $(OCTAVE) graphs.m "$(1)" png
endef

define get/history
      $(GH) -f '$(1)' -T '$(2)' -i '$(PERSERVER)' -H '$(3)' 1>"$(4)" 2> >(tee "$(5)" >&2)
endef

ifeq ($(ZABBIX_HISTORY),backup)
 GH += -B
endif
ifeq ($(ZABBIX_HISTORY),sql)
 GH += -S
endif

#Output dir
ifeq ($(OUTDIR),)
O=out
else
O=$(OUTDIR)
endif

FIND=find $(O) -name '*' 

define testtool
	@if ! which $(1) >/dev/null; then echo $(2); exit 2; fi
endef

define gettarget
 TS=$(shell echo $1 | cut -d '/' -f 2-); \
 T=$(basename $(shell echo $1 | cut -d '/' -f 2-)); \
 T2=$(basename $(basename $(shell echo $1 | cut -d '/' -f 2-)));
endef

# Parameter: host start_date interval start_time
define analyze/host/interval
 info-$(1)-$(2)-$(3):
	@echo -n get-$(1)-$(2)-$(3) analyze-$(1)-$(2)-$(3)
 get-$(1)-$(2)-$(3): $(O)/$(1)-$(2)-$(3).m
 analyze-$(1)-$(2)-$(3): $(O)/$(1)-$(2)-$(3).az
 graphs-$(1)-$(2)-$(3): $(O)/$(1)-$(2)-$(3).az
	@$(call analyze/octave/graphs,$(O)/$(1)-$(2)-$(3).az)
 $(1)-$(2)-$(3): $(O)/$(1)-$(2)-$(3).az
 $(O)/$(1)-$(2)-$(3).m:
	@echo "Getting history to $(O)/$(1)-$(2)-$(3).m"
	$(call get/history,$(4),$(3),$(1),$(O)/$(1)-$(2)-$(3).m.tmp,$(O)/$(1)-$(2)-$(3).m.log) && mv $(O)/$(1)-$(2)-$(3).m.tmp $(O)/$(1)-$(2)-$(3).m;
 $(O)/$(1)-$(2)-$(3).az: $(O)/$(1)-$(2)-$(3).m
	@echo "Analyzing $(O)/$(1)-$(2)-$(3).m";
	@$(call analyze/octave,$(O)/$(1)-$(2)-$(3).m,$(O)/$(1)-$(2)-$(3).az);
endef

# Parameter: host
define analyze/host
 TARGETS += get-$(1) analyze-$(1)
 info-$(1): $(foreach start_date,$(START_DATES_NICE),$(foreach interval,$(INTERVALS),info-$(1)-$(start_date)-$(interval)))
	@echo
 get-$(1): $(foreach start_date,$(START_DATES_NICE),$(foreach interval,$(INTERVALS),get-$(1)-$(start_date)-$(interval)))
 analyze-$(1): $(foreach start_date,$(START_DATES_NICE),$(foreach interval,$(INTERVALS),analyze-$(1)-$(start_date)-$(interval)))
 graphs-$(1): $(foreach start_date,$(START_DATES_NICE),$(foreach interval,$(INTERVALS),graphs-$(1)-$(start_date)-$(interval)))
 $(1): analyze-$(1)
 $(foreach start_date,$(START_DATES_NICE),$(foreach interval,$(INTERVALS),$(eval $(call analyze/host/interval,$(1),$(start_date),$(interval),$(start_date)))))
 clean-$(1):
	rm -rf $(O)/$(1)*
endef

ifneq ($(wildcard config.inc.php),)
 ifeq ($(HOSTS),)
  ifneq ($(HOSTGROUP),)
   HOSTS:=$(shell ./gethostsingroup.php $(HOSTGROUP))
   ifeq ($(HOSTS),)
     $(error HOSTGROUP $(HOSTGROUP) probably does not exists? Groups are case sensitive!)
   endif
  endif
 endif
endif

# Some default variables
ifeq ($(TIME_START),)
 TIME_START=00:00 -1 days
endif
ifeq ($(INTERVALS),)
 INTERVALS=1day
 TIME_TO=00:00
 TIME_STEP=1day
endif

START_DATES=$(TIME_START)
ifneq ($(TIME_TO),)
 ifneq ($(TIME_STEP),)
  START_DATES:=$(shell ./dateintervals.php -F '$(TIME_START)' -T '$(TIME_TO)' -S '$(TIME_STEP)' -t '3600' -D '@U')
  START_DATES_NICE:=$(shell ./dateintervals.php -F '$(TIME_START)' -T '$(TIME_TO)' -S '$(TIME_STEP)' -t '3600' -D 'Y_m_d_Hi')
 endif
endif

ifneq ($(START_DATES_NICE),)
 FIND += -and '(' $(foreach dte,$(START_DATES_NICE),-name '*-$(dte)-*' -o) -name 'nonpossible_file' ')'
endif

ifneq ($(HOSTS),)
  FIND += -and '(' $(foreach hst,$(HOSTS),-name '$(hst)-*' -o) -name 'nonpossible_host' ')'
endif

ifneq ($(INTERVALS),)
  FIND += -and '(' $(foreach int,$(INTERVALS),-name '*-$(int)\.*' -o) -name 'nonpossible_interval' ')'
endif

