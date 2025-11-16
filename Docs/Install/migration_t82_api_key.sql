-- --------------------------------------------------------------------
-- Flussu v4.5 - Database Migration
-- Migration: Add t82_api_key table for temporary API authentication
-- Date: 2025-02-16
-- --------------------------------------------------------------------
-- This migration adds support for temporary API keys used for
-- web interface authentication and API calls.
-- --------------------------------------------------------------------

-- Create table for temporary API keys
DROP TABLE IF EXISTS `t82_api_key`;
CREATE TABLE `t82_api_key` (
  `c82_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `c82_user_id` int(10) unsigned NOT NULL,
  `c82_key` varchar(128) NOT NULL,
  `c82_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c82_expires` datetime NOT NULL,
  `c82_used` datetime DEFAULT NULL,
  PRIMARY KEY (`c82_id`),
  UNIQUE KEY `UNQ_ApiKey` (`c82_key`),
  KEY `ix82_userid` (`c82_user_id`),
  KEY `ix82_expires` (`c82_expires`),
  CONSTRAINT `fk82_user` FOREIGN KEY (`c82_user_id`) REFERENCES `t80_user` (`c80_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migration completed successfully
SELECT 'Migration t82_api_key completed successfully' AS status;
