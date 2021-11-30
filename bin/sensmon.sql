-- MySQL dump 10.10
--
-- Host: waterdata.glwi.uwm.edu    Database: sensmon
-- ------------------------------------------------------
-- Server version	5.0.51a-3ubuntu5.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alarm_audit`
--

DROP TABLE IF EXISTS `alarm_audit`;
CREATE TABLE `alarm_audit` (
  `recid` bigint(20) NOT NULL auto_increment,
  `sensorid` bigint(20) NOT NULL,
  `recdate` datetime NOT NULL,
  `username` varchar(32) NOT NULL,
  `alarm_min` varchar(32) NOT NULL,
  `alarm_max` varchar(32) NOT NULL,
  PRIMARY KEY  (`recid`),
  KEY `sensorid` (`sensorid`,`recdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='who edited when';

--
-- Table structure for table `alarm_check_history`
--

DROP TABLE IF EXISTS `alarm_check_history`;
CREATE TABLE `alarm_check_history` (
  `recid` bigint(20) NOT NULL auto_increment,
  `sensorid` bigint(20) NOT NULL,
  `recdate` datetime NOT NULL,
  `alarm_min` varchar(32) NOT NULL,
  `alarm_max` varchar(32) NOT NULL,
  `data_value` varchar(32) NOT NULL,
  `alarm_trip_min` tinyint(4) default NULL,
  `alarm_trip_max` tinyint(4) default NULL,
  `alarm_trip_timeout` tinyint(4) default NULL,
  PRIMARY KEY  (`recid`),
  KEY `recdate` (`recdate`),
  KEY `sensorid` (`sensorid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='record of checks for alarm condition';

--
-- Table structure for table `sensor_config`
--

DROP TABLE IF EXISTS `sensor_config`;
CREATE TABLE `sensor_config` (
  `sensorid` bigint(20) NOT NULL auto_increment,
  `description` varchar(256) NOT NULL,
  `datatype_long` varchar(64) NOT NULL COMMENT 'e.g. ''meters''',
  `datatype_short` varchar(32) NOT NULL COMMENT 'e.g. ''m''',
  `database` varchar(32) NOT NULL default 'localhost',
  `table` varchar(32) NOT NULL,
  `datacolumn` varchar(32) NOT NULL,
  `datecolumn` varchar(32) NOT NULL default 'recdate',
  `alarm_min` varchar(32) NOT NULL,
  `alarm_max` varchar(32) NOT NULL,
  `alert_email` varchar(64) NOT NULL,
  `alarm_avg_time` varchar(32) NOT NULL,
  `alarm_min_readings` int(11) NOT NULL,
  `alarm_timeout_time` varchar(32) NOT NULL,
  PRIMARY KEY  (`sensorid`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

