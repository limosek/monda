--
-- Name: hostcorr; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE hostcorr (
    windowid1 bigint NOT NULL,
    windowid2 bigint NOT NULL,
    hostid1 bigint NOT NULL,
    hostid2 bigint NOT NULL,
    cnt bigint,
    corr double precision,
    loi integer DEFAULT 0
);


ALTER TABLE hostcorr OWNER TO monda;

SET default_with_oids = true;

--
-- Name: hoststat; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE hoststat (
    hostid bigint NOT NULL,
    windowid integer NOT NULL,
    cnt bigint DEFAULT 0,
    loi integer DEFAULT 0,
    updated timestamp with time zone,
    items integer DEFAULT 0
);


ALTER TABLE hoststat OWNER TO monda;

--
-- Name: itemcorr; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE itemcorr (
    windowid1 integer NOT NULL,
    windowid2 integer NOT NULL,
    itemid1 bigint NOT NULL,
    itemid2 bigint NOT NULL,
    corr double precision NOT NULL,
    cnt bigint,
    loi integer DEFAULT 0
);


ALTER TABLE itemcorr OWNER TO monda;

--
-- Name: itemstat; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE itemstat (
    itemid bigint NOT NULL,
    hostid bigint,
    windowid integer NOT NULL,
    avg_ double precision DEFAULT 0.0000 NOT NULL,
    min_ double precision DEFAULT 0.0000 NOT NULL,
    max_ double precision DEFAULT 0.0000 NOT NULL,
    stddev_ double precision DEFAULT 0.0000 NOT NULL,
    cv double precision DEFAULT 0.0000 NOT NULL,
    cnt bigint DEFAULT 0,
    loi integer DEFAULT 0
);


ALTER TABLE itemstat OWNER TO monda;

--
-- Name: s_timewindowid; Type: SEQUENCE; Schema: public; Owner: monda
--

CREATE SEQUENCE s_timewindowid
    START WITH 100
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE s_timewindowid OWNER TO monda;

--
-- Name: timewindow; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE timewindow (
    id integer DEFAULT nextval('s_timewindowid'::regclass) NOT NULL,
    parentid integer,
    serverid integer DEFAULT 1,
    description character varying(255) DEFAULT NULL::character varying,
    tfrom timestamp with time zone NOT NULL,
    seconds bigint NOT NULL,
    created timestamp with time zone NOT NULL,
    updated timestamp with time zone,
    found bigint,
    processed bigint,
    ignored bigint,
    stddev0 bigint,
    lowavg bigint,
    lowcnt bigint,
    loi integer DEFAULT 0,
    lowstddev integer,
    lowcv integer,
    avgcv double precision,
    avgcnt bigint
);


ALTER TABLE timewindow OWNER TO monda;

SET default_with_oids = false;

--
-- Name: windowcorr; Type: TABLE; Schema: public; Owner: monda; Tablespace: 
--

CREATE TABLE windowcorr (
    windowid1 bigint NOT NULL,
    windowid2 bigint NOT NULL,
    loi integer DEFAULT 0
);


ALTER TABLE windowcorr OWNER TO monda;

--
-- Name: p_hoststat; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY hoststat
    ADD CONSTRAINT p_hoststat PRIMARY KEY (hostid, windowid);


--
-- Name: p_itemcorr; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY itemcorr
    ADD CONSTRAINT p_itemcorr PRIMARY KEY (windowid1, windowid2, itemid1, itemid2);


--
-- Name: p_itemstat; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY itemstat
    ADD CONSTRAINT p_itemstat PRIMARY KEY (windowid, itemid);


--
-- Name: p_timewindow; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY timewindow
    ADD CONSTRAINT p_timewindow PRIMARY KEY (id);


--
-- Name: p_windowcorr; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY windowcorr
    ADD CONSTRAINT p_windowcorr PRIMARY KEY (windowid1, windowid2);


--
-- Name: primary; Type: CONSTRAINT; Schema: public; Owner: monda; Tablespace: 
--

ALTER TABLE ONLY hostcorr
    ADD CONSTRAINT "primary" PRIMARY KEY (windowid1, windowid2, hostid1, hostid2);


--
-- Name: fki_ic_itemid1; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_ic_itemid1 ON itemcorr USING btree (windowid1, itemid1);


--
-- Name: fki_ic_itemid2; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_ic_itemid2 ON itemcorr USING btree (itemid2, windowid2);


--
-- Name: fki_p_host1; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_p_host1 ON hostcorr USING btree (hostid1, windowid1);


--
-- Name: fki_p_hostid2; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_p_hostid2 ON hostcorr USING btree (windowid2, hostid2);


--
-- Name: fki_p_window2; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_p_window2 ON windowcorr USING btree (windowid2);


--
-- Name: fki_p_windowid; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX fki_p_windowid ON hoststat USING btree (windowid);


--
-- Name: hoststat_hostid_windowid_idx; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX hoststat_hostid_windowid_idx ON hoststat USING btree (hostid, windowid);


--
-- Name: i_desc; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_desc ON timewindow USING btree (description);


--
-- Name: i_hostid; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_hostid ON itemstat USING btree (hostid);


--
-- Name: i_id; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_id ON timewindow USING btree (id);


--
-- Name: i_loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_loi ON timewindow USING btree (loi);


--
-- Name: i_parentid; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_parentid ON timewindow USING btree (parentid);


--
-- Name: i_times; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE UNIQUE INDEX i_times ON timewindow USING btree (serverid, tfrom, seconds);


--
-- Name: i_window; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX i_window ON hostcorr USING btree (windowid1, windowid2, hostid1, hostid2);


--
-- Name: i_windowitem; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE UNIQUE INDEX i_windowitem ON itemstat USING btree (windowid, itemid);


--
-- Name: ic_loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX ic_loi ON itemcorr USING btree (loi);


--
-- Name: ic_windowitem; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX ic_windowitem ON itemcorr USING btree (windowid1, windowid2, itemid1, itemid2);


--
-- Name: ih_loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX ih_loi ON hoststat USING btree (loi);


--
-- Name: ihc_loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX ihc_loi ON hostcorr USING btree (loi);


--
-- Name: iwc_loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX iwc_loi ON windowcorr USING btree (loi);


--
-- Name: iwc_window; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX iwc_window ON windowcorr USING btree (windowid1, windowid2);


--
-- Name: loi; Type: INDEX; Schema: public; Owner: monda; Tablespace: 
--

CREATE INDEX loi ON itemstat USING btree (loi);


--
-- Name: fi_windowid; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemstat
    ADD CONSTRAINT fi_windowid FOREIGN KEY (windowid) REFERENCES timewindow(id);


--
-- Name: fi_windowid1; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemcorr
    ADD CONSTRAINT fi_windowid1 FOREIGN KEY (windowid1) REFERENCES timewindow(id);


--
-- Name: fi_windowid2; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemcorr
    ADD CONSTRAINT fi_windowid2 FOREIGN KEY (windowid2) REFERENCES timewindow(id);


--
-- Name: ic_itemid1; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemcorr
    ADD CONSTRAINT ic_itemid1 FOREIGN KEY (windowid1, itemid1) REFERENCES itemstat(windowid, itemid);


--
-- Name: ic_itemid2; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemcorr
    ADD CONSTRAINT ic_itemid2 FOREIGN KEY (itemid2, windowid2) REFERENCES itemstat(itemid, windowid);


--
-- Name: p_host1; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY hostcorr
    ADD CONSTRAINT p_host1 FOREIGN KEY (hostid1, windowid1) REFERENCES hoststat(hostid, windowid);


--
-- Name: p_hostid2; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY hostcorr
    ADD CONSTRAINT p_hostid2 FOREIGN KEY (windowid2, hostid2) REFERENCES hoststat(windowid, hostid);


--
-- Name: p_is_windowid; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY itemstat
    ADD CONSTRAINT p_is_windowid FOREIGN KEY (windowid) REFERENCES timewindow(id);


--
-- Name: p_window2; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY windowcorr
    ADD CONSTRAINT p_window2 FOREIGN KEY (windowid2) REFERENCES timewindow(id);


--
-- Name: p_windowid; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY hoststat
    ADD CONSTRAINT p_windowid FOREIGN KEY (windowid) REFERENCES timewindow(id);


--
-- Name: p_windowid1; Type: FK CONSTRAINT; Schema: public; Owner: monda
--

ALTER TABLE ONLY windowcorr
    ADD CONSTRAINT p_windowid1 FOREIGN KEY (windowid1) REFERENCES timewindow(id);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--
