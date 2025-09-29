-- MariaDB dump 10.19  Distrib 10.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: flussu_db
-- ------------------------------------------------------
-- Server version	10.8.6-MariaDB-1:10.8.6+maria~ubu2004-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `t00_version`
--

DROP TABLE IF EXISTS `t00_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t00_version` (
  `c00_version` varchar(5) NOT NULL,
  `c00_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`c00_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
inser into `t00_version` (`c00_version`, `c00_date`) values ('11', current_timestamp());
--
-- Table structure for table `t01_app`select t80
--

DROP TABLE IF EXISTS `t01_app`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t01_app` (
  `c01_wf_id` int(10) unsigned NOT NULL,
  `c01_logo` mediumtext NOT NULL,
  `c01_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c01_email` varchar(45) NOT NULL DEFAULT '',
  `c01_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c01_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c01_validfrom` datetime NOT NULL DEFAULT '1899-12-31 23:59:59',
  `c01_validuntil` datetime NOT NULL DEFAULT '1899-12-31 23:59:59',
  PRIMARY KEY (`c01_wf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t05_app_lang`
--

DROP TABLE IF EXISTS `t05_app_lang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t05_app_lang` (
  `c05_wf_id` int(10) unsigned NOT NULL,
  `c05_lang` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_title` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_website` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_whoweare` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_privacy` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_menu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_operative` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c05_openai` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c05_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c05_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c05_startprivacy` mediumtext DEFAULT '["view","accept"]',
  `c05_errors` mediumtext DEFAULT '["cache":"delete cache"]',
  `c05_langstart` mediumtext DEFAULT '["Choose a language","set language"]',
  PRIMARY KEY (`c05_wf_id`,`c05_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t100_timed_call`
--

DROP TABLE IF EXISTS `t100_timed_call`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t100_timed_call` (
  `c100_seq` bigint(20) NOT NULL AUTO_INCREMENT,
  `c100_sess_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c100_wid` int(10) NOT NULL,
  `c100_block_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `c100_send_data` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c100_start_date` datetime NOT NULL DEFAULT current_timestamp(),
  `c100_minutes` int(10) unsigned NOT NULL DEFAULT 60,
  `c100_enabled` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `c100_call_date` datetime NOT NULL DEFAULT '1899-12-31 23:59:59',
  `c100_call_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`c100_seq`),
  KEY `ix100_session` (`c100_sess_id`),
  KEY `ix100_enabled` (`c100_enabled`),
  KEY `ix100_timed` (`c100_start_date`,`c100_minutes`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t10_workflow`
--

DROP TABLE IF EXISTS `t10_workflow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t10_workflow` (
  `c10_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c10_wf_auid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `c10_name` varchar(128) NOT NULL DEFAULT 'undefined',
  `c10_app_code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c10_description` tinytext DEFAULT NULL,
  `c10_supp_langs` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'EN',
  `c10_def_lang` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'EN',
  `c10_userid` int(10) unsigned NOT NULL,
  `c10_svc1` text NOT NULL,
  `c10_svc2` text NOT NULL,
  `c10_svc3` text NOT NULL,
  `c10_validfrom` datetime DEFAULT '1899-12-31 23:59:59',
  `c10_validuntil` datetime DEFAULT '1899-12-31 23:59:59',
  `c10_active` int(2) unsigned NOT NULL DEFAULT 1,
  `c10_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c10_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c10_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c10_deleted_by` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c10_id`),
  UNIQUE KEY `c10_wf_auid` (`c10_wf_auid`)
) ENGINE=InnoDB AUTO_INCREMENT=286 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t15_workflow_backup`
--

DROP TABLE IF EXISTS `t15_workflow_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t15_workflow_backup` (
  `c15_backup_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c15_workflow_id` int(10) unsigned NOT NULL,
  `c15_workflow_json` longtext NOT NULL,
  `c15_rec_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`c15_backup_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t200_worker`
--

DROP TABLE IF EXISTS `t200_worker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t200_worker` (
  `c200_sess_id` varchar(36) NOT NULL COMMENT 'generato dall''applicaizone allo start del workflow',
  `c200_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `c200_wid` int(10) unsigned NOT NULL DEFAULT 0,
  `c200_lang` varchar(5) DEFAULT NULL,
  `c200_thisblock` varchar(36) NOT NULL DEFAULT '0',
  `c200_time_start` datetime NOT NULL DEFAULT current_timestamp(),
  `c200_state_error` int(2) unsigned NOT NULL DEFAULT 0,
  `c200_state_usererr` int(2) unsigned NOT NULL DEFAULT 0,
  `c200_state_exterr` int(2) unsigned NOT NULL DEFAULT 0,
  `c200_blk_end` int(10) unsigned DEFAULT NULL,
  `c200_time_end` datetime NOT NULL DEFAULT current_timestamp(),
  `c200_hduration` int(10) unsigned NOT NULL DEFAULT 1,
  `c200_subs` longtext DEFAULT NULL,
  `c200_user` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c200_sess_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t203_notifications`
--

DROP TABLE IF EXISTS `t203_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t203_notifications` (
  `c203_notify_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `c203_recdate` timestamp NOT NULL DEFAULT current_timestamp(),
  `c203_sess_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c203_n_type` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c203_n_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c203_n_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`c203_notify_id`),
  KEY `ix203_session` (`c203_sess_id`,`c203_recdate`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t205_work_var`
--

DROP TABLE IF EXISTS `t205_work_var`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t205_work_var` (
  `c205_sess_id` varchar(36) NOT NULL,
  `c205_elm_id` varchar(50) NOT NULL,
  `c205_elm_val` longtext DEFAULT NULL,
  `c205_elm_isarray` tinyint(2) NOT NULL DEFAULT 0,
  `c205_timestamp` timestamp NULL DEFAULT current_timestamp(),
  `c205_source` int(4) unsigned NOT NULL DEFAULT 0 COMMENT '0=user\n1=wofobo\n2=ext_svc',
  PRIMARY KEY (`c205_sess_id`,`c205_elm_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t207_history`
--

DROP TABLE IF EXISTS `t207_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t207_history` (
  `c207_sess_id` varchar(36) NOT NULL,
  `c207_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `c207_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c207_count` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`c207_sess_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t209_work_log`
--

DROP TABLE IF EXISTS `t209_work_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t209_work_log` (
  `c209_sess_id` varchar(36) NOT NULL,
  `c209_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `c209_tpinfo` int(4) unsigned NOT NULL DEFAULT 0 COMMENT '0= riga log - 1= user id - 2= indirizzo ip - 3= user agent - 4=internal error - 5=external error - 6=user error - 7= altre info speciali',
  `c209_row` mediumtext DEFAULT NULL,
  KEY `ix_t200_log` (`c209_sess_id`,`c209_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t20_block`
--

DROP TABLE IF EXISTS `t20_block`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t20_block` (
  `c20_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c20_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `c20_flofoid` int(10) unsigned NOT NULL,
  `c20_start` int(2) unsigned NOT NULL DEFAULT 0,
  `c20_type` varchar(3) DEFAULT NULL,
  `c20_desc` varchar(128) DEFAULT NULL,
  `c20_exec` mediumtext DEFAULT NULL,
  `c20_xpos` float DEFAULT 0,
  `c20_ypos` float DEFAULT 0,
  `c20_note` tinytext DEFAULT NULL,
  `c20_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `c20_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c20_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c20_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c20_deleted_by` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c20_id`),
  UNIQUE KEY `ix20_uuid` (`c20_uuid`),
  KEY `ix20_flofoid` (`c20_flofoid`)
) ENGINE=InnoDB AUTO_INCREMENT=8108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t210_openai_chat`
--

DROP TABLE IF EXISTS `t210_openai_chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t210_openai_chat` (
  `c210_sess_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `c210_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`c210_sess_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t25_blockexit`
--

DROP TABLE IF EXISTS `t25_blockexit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t25_blockexit` (
  `c25_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c25_blockid` int(10) unsigned NOT NULL,
  `c25_nexit` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `c25_direction` int(10) unsigned NOT NULL DEFAULT 0,
  `c25_description` varchar(45) NOT NULL DEFAULT '',
  `c25_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c25_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c25_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c25_deleted_by` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c25_id`),
  KEY `ix25_bid` (`c25_blockid`)
) ENGINE=InnoDB AUTO_INCREMENT=16795 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t300_book`
--

DROP TABLE IF EXISTS `t300_book`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t300_book` (
  `c300_cal_id` int(11) NOT NULL,
  `c300_book_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `c300_start` datetime NOT NULL,
  `c300_end` datetime NOT NULL,
  `c300_units` int(10) unsigned NOT NULL DEFAULT 1,
  `c300_datereg` datetime NOT NULL DEFAULT current_timestamp(),
  `c300_title` varchar(128) NOT NULL,
  `c300_desc` varchar(512) DEFAULT NULL,
  `c300_place` varchar(128) DEFAULT NULL,
  `c300_notes` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`c300_book_id`),
  KEY `ix_book_start` (`c300_start`),
  KEY `ix_book` (`c300_cal_id`,`c300_book_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf16 COLLATE=utf16_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t30_blk_elm`
--

DROP TABLE IF EXISTS `t30_blk_elm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t30_blk_elm` (
  `c30_elemid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c30_blockid` int(10) unsigned NOT NULL,
  `c30_uuid` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `c30_varname` varchar(128) NOT NULL DEFAULT '',
  `c30_type` int(3) unsigned NOT NULL DEFAULT 0 COMMENT '''0''=MESSAGE, ''1''=INPUT, ''2''=BUTTON, ''3''=MEDIA(URL), ''4''=LINK(URL), ''5''=TXT_ASSIGN',
  `c30_exit_num` int(10) unsigned DEFAULT NULL,
  `c30_css` text DEFAULT NULL,
  `c30_order` int(3) unsigned NOT NULL DEFAULT 0,
  `c30_note` tinytext DEFAULT NULL,
  `c30_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c30_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c30_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c30_deleted_by` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c30_elemid`,`c30_blockid`),
  UNIQUE KEY `ix30_uuid` (`c30_uuid`),
  KEY `ix30_bid` (`c30_blockid`)
) ENGINE=InnoDB AUTO_INCREMENT=13352 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t310_calendar`
--

DROP TABLE IF EXISTS `t310_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t310_calendar` (
  `c310_cal_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c310_belong_uid` int(10) unsigned NOT NULL,
  `c310_name` varchar(128) NOT NULL,
  `c310_min_minutes` int(10) unsigned NOT NULL DEFAULT 15,
  `c310_max_book_unit` int(10) unsigned NOT NULL DEFAULT 1,
  `c310_desc` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`c310_cal_id`),
  KEY `ix_user` (`c310_belong_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t313_cal_state`
--

DROP TABLE IF EXISTS `t313_cal_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t313_cal_state` (
  `c313_cal_id` int(10) unsigned NOT NULL,
  `c313_weekday` tinyint(3) unsigned NOT NULL,
  `c313_start` time NOT NULL,
  `c313_end` time NOT NULL,
  `c313_status` tinyint(3) unsigned NOT NULL,
  KEY `ix_calendar` (`c313_cal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t315_cal_dtexc`
--

DROP TABLE IF EXISTS `t315_cal_dtexc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t315_cal_dtexc` (
  `c315_cal_id` int(10) unsigned NOT NULL,
  `c315_start` datetime NOT NULL,
  `c315_end` datetime NOT NULL,
  `c315_status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  UNIQUE KEY `ix_calendar` (`c315_cal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t40_element`
--

DROP TABLE IF EXISTS `t40_element`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t40_element` (
  `c40_id` int(10) unsigned NOT NULL,
  `c40_lang` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'EN',
  `c40_text` mediumtext DEFAULT NULL,
  `c40_url` varchar(255) DEFAULT NULL,
  `c40_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c40_modified` timestamp NOT NULL DEFAULT current_timestamp(),
  `c40_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c40_deleted_by` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c40_id`,`c40_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t50_otcmd`
--

DROP TABLE IF EXISTS `t50_otcmd`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t50_otcmd` (
  `c50_id` int(11) NOT NULL AUTO_INCREMENT,
  `c50_key` varchar(36) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `c50_command` varchar(50) NOT NULL,
  `c50_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `c50_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`c50_id`),
  KEY `ix_Key` (`c50_key`)
) ENGINE=InnoDB AUTO_INCREMENT=323 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t60_multi_flow`
--

DROP TABLE IF EXISTS `t60_multi_flow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t60_multi_flow` (
  `c60_id` varchar(15) NOT NULL,
  `c60_workflow_id` int(10) unsigned DEFAULT NULL,
  `c60_user_id` int(10) unsigned DEFAULT 0,
  `c60_email` varchar(45) NOT NULL,
  `c60_json_data` text NOT NULL,
  `c60_assigned_server` varchar(25) DEFAULT 'srv02.flu.lu',
  `c60_date_from` datetime NOT NULL DEFAULT current_timestamp(),
  `c60_date_to` datetime NOT NULL DEFAULT '2099-12-31 23:59:59',
  `c60_deleted` int(1) unsigned DEFAULT 0,
  `c60_open_count` int(10) unsigned DEFAULT 0,
  `c60_used_count` int(10) unsigned DEFAULT 0,
  `c60_mail_count` int(10) unsigned DEFAULT 0,
  `c60_count_summary` text DEFAULT NULL,
  PRIMARY KEY (`c60_id`),
  KEY `ix_wfid` (`c60_workflow_id`),
  KEY `ix_cusid` (`c60_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t70_stat`
--

DROP TABLE IF EXISTS `t70_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t70_stat` (
  `c70_wid` int(10) unsigned NOT NULL,
  `c70_sid` varchar(36) NOT NULL,
  `c70_bid` int(10) unsigned NOT NULL,
  `c70_start` smallint(6) NOT NULL DEFAULT 0,
  `c70_channel` int(2) unsigned NOT NULL,
  `c70_timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `c70_data` mediumtext NOT NULL,
  `c70_tag` varchar(2) NOT NULL DEFAULT '' COMMENT 'internal use',
  KEY `ix_wid` (`c70_wid`),
  KEY `ix_wid_sid` (`c70_wid`,`c70_sid`) USING BTREE,
  KEY `ix_exec` (`c70_timestamp`) USING BTREE,
  KEY `ix_start` (`c70_wid`,`c70_sid`,`c70_start`),
  KEY `ix_stat` (`c70_start`,`c70_tag`),
  KEY `ix_tag` (`c70_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t71_access`
--

DROP TABLE IF EXISTS `t71_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t71_access` (
  `c71_wid` int(10) unsigned NOT NULL,
  `c71_sid` varchar(36) NOT NULL,
  `c71_date` datetime NOT NULL,
  `c71_chan` tinyint(4) NOT NULL,
  `c71_oth` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`c71_wid`,`c71_sid`),
  KEY `ix_71_date` (`c71_date`) USING BTREE,
  KEY `ix_71_chan` (`c71_chan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t80_user`
--

DROP TABLE IF EXISTS `t80_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t80_user` (
  `c80_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c80_email` varchar(65) NOT NULL,
  `c80_username` varchar(65) NOT NULL,
  `c80_password` varchar(250) NOT NULL,
  `c80_pwd_chng` datetime NOT NULL DEFAULT current_timestamp(),
  `c80_role` int(4) unsigned DEFAULT 0,
  `c80_name` varchar(60) DEFAULT NULL,
  `c80_surname` varchar(60) DEFAULT NULL,
  `c80_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c80_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `c80_deleted` datetime DEFAULT '1899-12-31 23:59:59',
  `c80_deleted_by` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`c80_id`),
  UNIQUE KEY `UNQ_UserName` (`c80_username`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
insert into t80_user (c80_id, c80_email, c80_username, c80_password, c80_pwd_chng, c80_role, c80_name, c80_surname, c80_created, c80_modified, c80_deleted, c80_deleted_by) values (16, 'admin@example.com', 'admin', '', current_timestamp(), 1, 'User', 'Admin', current_timestamp(), current_timestamp(), '1899-12-31 23:59:59', 0);
--
-- Table structure for table `t83_project`
--

DROP TABLE IF EXISTS `t83_project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t83_project` (
  `c83_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c83_desc` varchar(128) NOT NULL,
  `c83_note` mediumtext DEFAULT NULL,
  `c83_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c83_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`c83_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t85_prj_wflow`
--

DROP TABLE IF EXISTS `t85_prj_wflow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t85_prj_wflow` (
  `c85_prj_id` int(10) unsigned NOT NULL,
  `c85_flofoid` int(10) unsigned NOT NULL,
  UNIQUE KEY `ix_t85` (`c85_prj_id`,`c85_flofoid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t87_prj_user`
--

DROP TABLE IF EXISTS `t87_prj_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t87_prj_user` (
  `c87_prj_id` int(10) unsigned NOT NULL,
  `c87_usr_id` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `t90_role`
--

DROP TABLE IF EXISTS `t90_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t90_role` (
  `c90_id` int(4) unsigned NOT NULL,
  `c90_name` varchar(30) NOT NULL,
  `c90_crud` varchar(5) NOT NULL DEFAULT 'CRUDX',
  PRIMARY KEY (`c90_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v10_wf_prj`
--
CREATE VIEW `v10_wf_prj` AS select `wf`.`c10_id` AS `wf_id`,ifnull(`pw`.`c85_prj_id`,0) AS `prj_id` from ((`t10_workflow` `wf` left join `t85_prj_wflow` `pw` on(`pw`.`c85_flofoid` = `wf`.`c10_id`)) left join `t83_project` `pr` on(`pr`.`c83_id` = `pw`.`c85_prj_id`));
CREATE VIEW `v15_prj_wf_usr` AS select `v2`.`wf_id` AS `wf_id`,`v2`.`prj_id` AS `prj_id`,ifnull(`p`.`c83_desc`,'@GENERIC') AS `c83_desc`,`w2`.`c10_name` AS `c10_name`,ifnull(`u`.`c87_usr_id`,`w2`.`c10_userid`) AS `c87_usr_id` from (((`t10_workflow` `w2` left join `v10_wf_prj` `v2` on(`w2`.`c10_id` = `v2`.`wf_id`)) left join `t83_project` `p` on(`p`.`c83_id` = `v2`.`prj_id`)) left join `t87_prj_user` `u` on(`u`.`c87_prj_id` = `v2`.`prj_id`)) order by ifnull(`p`.`c83_desc`,'@GENERIC'),`w2`.`c10_name`;
CREATE VIEW `v20_prj_wf_all` AS select `v15_prj_wf_usr`.`wf_id` AS `wf_id`,`v15_prj_wf_usr`.`prj_id` AS `prj_id`,`v15_prj_wf_usr`.`c83_desc` AS `prj_name`,`v15_prj_wf_usr`.`c10_name` AS `wf_name`,`v15_prj_wf_usr`.`c87_usr_id` AS `wf_user`,`t80_user`.`c80_email` AS `usr_email`,`t80_user`.`c80_name` AS `usr_name`,`t10_workflow`.`c10_active` AS `wf_active`,`t10_workflow`.`c10_deleted` AS `dt_deleted`,`t10_workflow`.`c10_validfrom` AS `dt_validfrom`,`t10_workflow`.`c10_validuntil` AS `dt_validuntil` from ((`v15_prj_wf_usr` join `t10_workflow` on(`v15_prj_wf_usr`.`wf_id` = `t10_workflow`.`c10_id`)) join `t80_user` on(`t80_user`.`c80_id` = `v15_prj_wf_usr`.`c87_usr_id`)) order by `v15_prj_wf_usr`.`c83_desc`,`v15_prj_wf_usr`.`c10_name`;
