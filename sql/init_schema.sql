
CREATE SEQUENCE s_timewindowid
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 100
  CACHE 1;
ALTER TABLE s_timewindowid
  OWNER TO monda;

CREATE TABLE timewindow
(
  id integer NOT NULL DEFAULT nextval('s_timewindowid'::regclass),
  parentid integer DEFAULT NULL,
  serverid integer DEFAULT 1,
  description character varying(255) DEFAULT NULL::character varying,
  tfrom timestamp with time zone NOT NULL,
  seconds bigint NOT NULL,
  created timestamp with time zone NOT NULL,
  updated timestamp with time zone DEFAULT NULL,
  found bigint DEFAULT NULL,
  processed bigint DEFAULT NULL,
  ignored bigint DEFAULT NULL,
  stddev0 bigint DEFAULT NULL,
  lowavg bigint DEFAULT NULL,
  lowcnt bigint DEFAULT NULL,
  loi integer DEFAULT 0,
  CONSTRAINT "p_timewindow" PRIMARY KEY (id)
)
WITH (
  OIDS=TRUE
);
ALTER TABLE timewindow
  OWNER TO monda;
CREATE INDEX i_id
  ON timewindow
  USING btree
  (id);
CREATE INDEX i_parentid
  ON timewindow
  USING btree
  (parentid);
CREATE UNIQUE INDEX i_times
  ON timewindow
  USING btree
  (serverid,tfrom,seconds);
CREATE INDEX i_loi
  ON timewindow
  USING btree
  (loi);
CREATE INDEX i_desc
  ON timewindow
  USING btree
  (description COLLATE pg_catalog."default");


CREATE TABLE itemstat
(
  itemid bigint NOT NULL,
  hostid bigint,
  windowid integer NOT NULL,
  avg_ double precision NOT NULL DEFAULT 0.0000,
  min_ double precision NOT NULL DEFAULT 0.0000,
  max_ double precision NOT NULL DEFAULT 0.0000,
  stddev_ double precision NOT NULL DEFAULT 0.0000,
  cv double precision NOT NULL DEFAULT 0.0000,
  cnt bigint DEFAULT 0,
  loi integer DEFAULT 0,
  CONSTRAINT "p_itemstat" PRIMARY KEY (windowid,itemid)
)
WITH (
  OIDS=TRUE
);
ALTER TABLE itemstat
  OWNER TO monda;
CREATE UNIQUE INDEX i_windowitem
  ON itemstat
  USING btree
  (windowid,itemid);
CREATE INDEX i_hostid
  ON itemstat
  USING btree
  (hostid);
CREATE INDEX loi
  ON itemstat
  USING btree
  (loi);
ALTER TABLE itemstat
  ADD CONSTRAINT fi_windowid FOREIGN KEY (windowid) REFERENCES timewindow (id)
   ON UPDATE NO ACTION ON DELETE NO ACTION;

CREATE TABLE hoststat
(
  hostid bigint NOT NULL,
  windowid integer NOT NULL,
  cnt bigint DEFAULT 0,
  loi integer DEFAULT 0,
  updated timestamp with time zone,
  CONSTRAINT p_hoststat PRIMARY KEY (hostid, windowid)
)
WITH (
  OIDS=TRUE
);
ALTER TABLE hoststat
  OWNER TO monda;

CREATE UNIQUE INDEX i_windowhost
  ON hoststat
  USING btree
  (windowid, hostid);


CREATE INDEX ih_loi
  ON hoststat
  USING btree
  (loi);

CREATE TABLE itemcorr
(
  windowid1 integer NOT NULL,
  windowid2 integer NOT NULL,
  itemid1 bigint NOT NULL,
  itemid2 bigint NOT NULL,
  corr double precision NOT NULL,
  cnt bigint,
  loi integer DEFAULT 0,
  CONSTRAINT "p_itemcorr" PRIMARY KEY (windowid1, windowid2, itemid1, itemid2)
)
WITH (
  OIDS=TRUE
);
ALTER TABLE itemcorr
  OWNER TO monda;
CREATE INDEX ic_windowitem
  ON itemcorr
  USING btree
  (windowid1, windowid2, itemid1, itemid2);
CREATE INDEX ic_loi
  ON itemcorr
  USING btree
  (loi);
ALTER TABLE itemcorr
  ADD CONSTRAINT fi_windowid1 FOREIGN KEY (windowid1) REFERENCES timewindow (id)
   ON UPDATE NO ACTION ON DELETE NO ACTION;
ALTER TABLE itemcorr
  ADD CONSTRAINT fi_windowid2 FOREIGN KEY (windowid2) REFERENCES timewindow (id)
   ON UPDATE NO ACTION ON DELETE NO ACTION;

CREATE TABLE hostcorr
(
  windowid1 bigint NOT NULL,
  windowid2 bigint NOT NULL,
  hostid1 bigint NOT NULL,
  hostid2 bigint NOT NULL,
  cnt bigint,
  corr double precision,
  loi integer DEFAULT 0, 
  CONSTRAINT "primary" PRIMARY KEY (windowid1, windowid2, hostid1, hostid2)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE hostcorr
  OWNER TO monda;
CREATE INDEX i_window
  ON hostcorr
  USING btree
  (windowid1,windowid2,hostid1,hostid2);
CREATE INDEX ihc_loi 
ON hostcorr
USING btree
 (loi);
ALTER TABLE hostcorr
  ADD CONSTRAINT fi_windowid1 FOREIGN KEY (windowid1) REFERENCES timewindow (id)
   ON UPDATE NO ACTION ON DELETE NO ACTION;
ALTER TABLE hostcorr
  ADD CONSTRAINT fi_windowid2 FOREIGN KEY (windowid2) REFERENCES timewindow (id)
   ON UPDATE NO ACTION ON DELETE NO ACTION;

CREATE TABLE windowcorr
(
  windowid1 bigint NOT NULL,
  windowid2 bigint NOT NULL,
  loi integer DEFAULT 0,
  CONSTRAINT "p_windowcorr" PRIMARY KEY (windowid1, windowid2)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE windowcorr
  OWNER TO monda;
CREATE INDEX iwc_window
  ON windowcorr
  USING btree
  (windowid1, windowid2);
CREATE INDEX iwc_loi
  ON windowcorr
  USING btree
  (loi);



