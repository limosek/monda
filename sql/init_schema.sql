
CREATE SEQUENCE public.host_group_id_seq;

CREATE TABLE public.host_group (
                host_group_id INTEGER NOT NULL DEFAULT nextval('public.host_group_id_seq'),
                name VARCHAR NOT NULL,
                CONSTRAINT p_hostgroup PRIMARY KEY (host_group_id)
);


ALTER SEQUENCE public.host_group_id_seq OWNED BY public.host_group.host_group_id;

CREATE SEQUENCE public.item_group_id_seq;

CREATE TABLE public.item_group (
                item_group_id INTEGER NOT NULL DEFAULT nextval('public.item_group_id_seq'),
                name VARCHAR NOT NULL,
                CONSTRAINT p_itemgroup PRIMARY KEY (item_group_id)
);


ALTER SEQUENCE public.item_group_id_seq OWNED BY public.item_group.item_group_id;

CREATE SEQUENCE public.zabbix_server_zabbix_server_id_seq;

CREATE TABLE public.zabbix_server (
                zabbix_server_id INTEGER NOT NULL DEFAULT nextval('public.zabbix_server_zabbix_server_id_seq'),
                description VARCHAR NOT NULL,
                sqluser VARCHAR NOT NULL,
                sqlpassword VARCHAR NOT NULL,
                apiuser VARCHAR NOT NULL,
                apipassword VARCHAR NOT NULL,
                url VARCHAR NOT NULL,
                apiurl VARCHAR NOT NULL,
                CONSTRAINT p_zabbix_server_id PRIMARY KEY (zabbix_server_id)
);


ALTER SEQUENCE public.zabbix_server_zabbix_server_id_seq OWNED BY public.zabbix_server.zabbix_server_id;

CREATE SEQUENCE public.host_host_id_seq;

CREATE TABLE public.host (
                host_id INTEGER NOT NULL DEFAULT nextval('public.host_host_id_seq'),
                item_group_id INTEGER NOT NULL,
                zabbix_server_id INTEGER NOT NULL,
                zabbix_hostid BIGINT NOT NULL,
                name VARCHAR NOT NULL,
                CONSTRAINT p_host PRIMARY KEY (host_id)
);


ALTER SEQUENCE public.host_host_id_seq OWNED BY public.host.host_id;

CREATE UNIQUE INDEX zabbix_host_idx
 ON public.host
 ( zabbix_server_id, zabbix_hostid );

CREATE TABLE public.host_group_list (
                host_group_id INTEGER NOT NULL,
                host_id INTEGER NOT NULL,
                CONSTRAINT p_hostgrouplist PRIMARY KEY (host_group_id)
);


CREATE TABLE public.item (
                item_id INTEGER NOT NULL,
                zabbix_server_id INTEGER NOT NULL,
                zabbix_itemid BIGINT NOT NULL,
                name VARCHAR NOT NULL,
                CONSTRAINT p_item PRIMARY KEY (item_id)
);


CREATE UNIQUE INDEX item_idx
 ON public.item
 ( zabbix_server_id, zabbix_itemid );

CREATE TABLE public.item_group_list (
                item_group_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                CONSTRAINT p_itemgrouplist PRIMARY KEY (item_group_id, item_id)
);


CREATE SEQUENCE public.item_stat_item_stat_id_seq;

CREATE TABLE public.item_stat (
                item_stat_id BIGINT NOT NULL DEFAULT nextval('public.item_stat_item_stat_id_seq'),
                avg_ NUMERIC(14,2) NOT NULL,
                min_ NUMERIC(14,2) NOT NULL,
                max_ NUMERIC(14,2) NOT NULL,
                stddev_ NUMERIC(14,2) NOT NULL,
                cv NUMERIC(14,2) NOT NULL,
                cnt BIGINT DEFAULT 0,
                CONSTRAINT p_item_stat PRIMARY KEY (item_stat_id)
);


ALTER SEQUENCE public.item_stat_item_stat_id_seq OWNED BY public.item_stat.item_stat_id;

CREATE UNIQUE INDEX i_windowitem
 ON public.item_stat USING BTREE
 ( item_stat_id );

CREATE SEQUENCE public.tw_tw_id_seq;

CREATE TABLE public.tw (
                tw_id BIGINT NOT NULL DEFAULT nextval('public.tw_tw_id_seq'),
                zabbix_server_id INTEGER NOT NULL,
                description VARCHAR(255) DEFAULT NULL::character varying,
                tfrom TIMESTAMP NOT NULL,
                seconds BIGINT NOT NULL,
                created TIMESTAMP NOT NULL,
                updated TIMESTAMP,
                found INTEGER NOT NULL,
                lowstddev INTEGER NOT NULL,
                lowavg INTEGER NOT NULL,
                lowcnt INTEGER NOT NULL,
                loi INTEGER DEFAULT 0,
                ignored INTEGER NOT NULL,
                processed INTEGER NOT NULL,
                parentid BIGINT,
                lowcv INTEGER NOT NULL,
                CONSTRAINT p_tw PRIMARY KEY (tw_id)
);
COMMENT ON TABLE public.tw IS 'Contains all informations about time windows. 
All statistics are window based.';


ALTER SEQUENCE public.tw_tw_id_seq OWNED BY public.tw.tw_id;

CREATE UNIQUE INDEX i_times
 ON public.tw USING BTREE
 ( zabbix_server_id, tfrom, seconds );

CREATE INDEX i_desc
 ON public.tw USING BTREE
 ( description );

CREATE INDEX i_id
 ON public.tw USING BTREE
 ( tw_id );

CREATE INDEX i_loi
 ON public.tw USING BTREE
 ( loi );

CREATE INDEX i_parentid
 ON public.tw USING BTREE
 ( parentid );

CREATE TABLE public.item_corr (
                tw1_id BIGINT NOT NULL,
                tw2_id BIGINT NOT NULL,
                item1_id INTEGER NOT NULL,
                item2_id INTEGER NOT NULL,
                corr NUMERIC(3,2) NOT NULL,
                cnt INTEGER NOT NULL,
                loi INTEGER NOT NULL,
                CONSTRAINT p_item_corr PRIMARY KEY (tw1_id, tw2_id, item1_id, item2_id)
);


CREATE INDEX item_corr_idx
 ON public.item_corr
 ( corr );

CREATE TABLE public.tw_corr (
                tw1_id BIGINT NOT NULL,
                tw2_id BIGINT NOT NULL,
                loi INTEGER DEFAULT 0 NOT NULL,
                corr NUMERIC(3,2) NOT NULL,
                CONSTRAINT p_tw_corr PRIMARY KEY (tw1_id, tw2_id)
);
COMMENT ON TABLE public.tw_corr IS 'Informations about correlations between timewindows.';


CREATE INDEX iwc_loi
 ON public.tw_corr USING BTREE
 ( loi );

CREATE INDEX iwc_window
 ON public.tw_corr USING BTREE
 ( tw1_id, tw2_id );

CREATE TABLE public.host_stat (
                host_id INTEGER NOT NULL,
                tw_id BIGINT NOT NULL,
                cnt INTEGER NOT NULL,
                item_group_id INTEGER NOT NULL,
                loi INTEGER DEFAULT 0 NOT NULL,
                CONSTRAINT p_host_stat PRIMARY KEY (host_id, tw_id)
);


CREATE UNIQUE INDEX i_windowhost
 ON public.host_stat USING BTREE
 ( tw_id, host_id );

CREATE INDEX ih_loi
 ON public.host_stat USING BTREE
 ( loi );

CREATE TABLE public.host_corr (
                tw1_id BIGINT NOT NULL,
                tw2_id BIGINT NOT NULL,
                host1_id INTEGER NOT NULL,
                host2_id INTEGER NOT NULL,
                cnt INTEGER NOT NULL,
                corr NUMERIC(3,2) NOT NULL,
                loi INTEGER DEFAULT 0,
                CONSTRAINT p_hostcorr PRIMARY KEY (tw1_id, tw2_id, host1_id, host2_id)
);


CREATE INDEX i_window
 ON public.host_corr USING BTREE
 ( tw1_id, tw2_id, host1_id, host2_id );

CREATE INDEX ihc_loi
 ON public.host_corr USING BTREE
 ( loi );

CREATE INDEX host_corr_idx
 ON public.host_corr
 ( loi );

CREATE TABLE public.tw_item (
                item_id INTEGER NOT NULL,
                tw_id BIGINT NOT NULL,
                item_stat_id BIGINT NOT NULL,
                CONSTRAINT p_tw_item PRIMARY KEY (item_id, tw_id)
);


ALTER TABLE public.host_group_list ADD CONSTRAINT host_group_host_group_list_fk
FOREIGN KEY (host_group_id)
REFERENCES public.host_group (host_group_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item_group_list ADD CONSTRAINT item_group_item_group_list_fk
FOREIGN KEY (item_group_id)
REFERENCES public.item_group (item_group_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host ADD CONSTRAINT item_group_host_fk
FOREIGN KEY (item_group_id)
REFERENCES public.item_group (item_group_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.tw ADD CONSTRAINT zabbix_server_tw_fk
FOREIGN KEY (zabbix_server_id)
REFERENCES public.zabbix_server (zabbix_server_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host ADD CONSTRAINT zabbix_server_host_fk
FOREIGN KEY (zabbix_server_id)
REFERENCES public.zabbix_server (zabbix_server_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item ADD CONSTRAINT zabbix_server_item_fk
FOREIGN KEY (zabbix_server_id)
REFERENCES public.zabbix_server (zabbix_server_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host_corr ADD CONSTRAINT host_hostcorr_fk
FOREIGN KEY (host1_id)
REFERENCES public.host (host_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host_stat ADD CONSTRAINT host_hoststat_fk
FOREIGN KEY (host_id)
REFERENCES public.host (host_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host_corr ADD CONSTRAINT host_host_corr_fk
FOREIGN KEY (host2_id)
REFERENCES public.host (host_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host_group_list ADD CONSTRAINT host_host_group_list_fk
FOREIGN KEY (host_id)
REFERENCES public.host (host_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE public.tw_item ADD CONSTRAINT item_timewindowitem_fk
FOREIGN KEY (item_id)
REFERENCES public.item (item_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item_corr ADD CONSTRAINT item_item_corr_fk
FOREIGN KEY (item1_id)
REFERENCES public.item (item_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item_corr ADD CONSTRAINT item_item_corr_fk1
FOREIGN KEY (item2_id)
REFERENCES public.item (item_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item_group_list ADD CONSTRAINT item_item_group_list_fk
FOREIGN KEY (item_id)
REFERENCES public.item (item_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.tw_item ADD CONSTRAINT itemstat_timewindowitem_fk
FOREIGN KEY (item_stat_id)
REFERENCES public.item_stat (item_stat_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE public.tw_item ADD CONSTRAINT timewindow_timewindowitem_fk
FOREIGN KEY (tw_id)
REFERENCES public.tw (tw_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.host_corr ADD CONSTRAINT timewindow_hostcorr_fk
FOREIGN KEY (tw1_id)
REFERENCES public.tw (tw_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE public.host_corr ADD CONSTRAINT timewindow_hostcorr_fk1
FOREIGN KEY (tw2_id)
REFERENCES public.tw (tw_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE public.host_stat ADD CONSTRAINT timewindow_hoststat_fk
FOREIGN KEY (tw_id)
REFERENCES public.tw (tw_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.tw_corr ADD CONSTRAINT timewindow_windowcorr_fk
FOREIGN KEY (tw1_id)
REFERENCES public.tw (tw_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE public.item_corr ADD CONSTRAINT timewindow_itemcorr_fk
FOREIGN KEY (tw1_id)
REFERENCES public.tw (tw_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.item_corr ADD CONSTRAINT timewindow_itemcorr_fk1
FOREIGN KEY (tw2_id)
REFERENCES public.tw (tw_id)
ON DELETE CASCADE
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE public.tw_corr ADD CONSTRAINT timewindow_timewindowcorr_fk
FOREIGN KEY (tw2_id)
REFERENCES public.tw (tw_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;