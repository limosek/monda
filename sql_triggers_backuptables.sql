
CREATE TABLE IF NOT EXISTS `history_backup` (
  `itemid` bigint(20) unsigned NOT NULL,
  `clock` int(11) NOT NULL DEFAULT '0',
  `value` double(16,4) NOT NULL DEFAULT '0.0000',
  `ns` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM;

ALTER TABLE history_backup PARTITION BY KEY(clock) PARTITIONS 20;

DROP TRIGGER IF EXISTS `history_backup`;
DELIMITER //
CREATE TRIGGER `history_backup` AFTER INSERT ON `history`
 FOR EACH ROW begin
  insert into history_backup (itemid,clock,value,ns) values (new.itemid, new.clock,new.value,new.ns);
end
//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `history_uint_backup` (
  `itemid` bigint(20) unsigned NOT NULL,
  `clock` int(11) NOT NULL DEFAULT '0',
  `value` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ns` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM ;

ALTER TABLE history_uint_backup PARTITION BY KEY(clock) PARTITIONS 20;

DROP TRIGGER IF EXISTS `history_uint_backup`;
DELIMITER //
CREATE TRIGGER `history_uint_backup` AFTER INSERT ON `history_uint`
 FOR EACH ROW begin
  insert into history_uint_backup (itemid,clock,value,ns) values (new.itemid, new.clock,new.value,new.ns);
end
//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `trends_backup` (
  `itemid` bigint(20) unsigned NOT NULL,
  `clock` int(11) NOT NULL DEFAULT '0',
  `num` int(11) NOT NULL DEFAULT '0',
  `value_min` double(16,4) NOT NULL DEFAULT '0.0000',
  `value_avg` double(16,4) NOT NULL DEFAULT '0.0000',
  `value_max` double(16,4) NOT NULL DEFAULT '0.0000'
) ENGINE=myisam ;

ALTER TABLE trends_backup PARTITION BY KEY(clock) PARTITIONS 20;

DROP TRIGGER IF EXISTS `trends_backup`;
DELIMITER //
CREATE TRIGGER `trends_backup` AFTER INSERT ON `trends`
 FOR EACH ROW begin
  insert into trends_backup (itemid,clock,num,value_min,value_avg,value_max) values (new.itemid, new.clock, new.num, new.value_min, new.value_avg, new.value_max);
end
//
DELIMITER ;

CREATE TABLE IF NOT EXISTS `trends_uint_backup` (
  `itemid` bigint(20) unsigned NOT NULL,
  `clock` int(11) NOT NULL DEFAULT '0',
  `num` int(11) NOT NULL DEFAULT '0',
  `value_min` bigint(20) unsigned NOT NULL DEFAULT '0',
  `value_avg` bigint(20) unsigned NOT NULL DEFAULT '0',
  `value_max` bigint(20) unsigned NOT NULL DEFAULT '0'
) ENGINE=myisam ;

ALTER TABLE trends_uint_backup PARTITION BY KEY(clock) PARTITIONS 20;

DROP TRIGGER IF EXISTS `trends_uint_backup`;
DELIMITER //
CREATE TRIGGER `trends_uint_backup` AFTER INSERT ON `trends_uint`
 FOR EACH ROW begin
  insert into trends_uint_backup (itemid,clock,num,value_min,value_avg,value_max) values (new.itemid, new.clock, new.num, new.value_min, new.value_avg, new.value_max);
end
//
DELIMITER ;

