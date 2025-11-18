-- Migration: Add password recovery token table
-- Date: 2025-11-18
-- Description: Creates table for password recovery tokens with security features

-- Table structure for table `t81_pwd_recovery`
--

DROP TABLE IF EXISTS `t81_pwd_recovery`;
CREATE TABLE `t81_pwd_recovery` (
  `c81_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c81_user_id` int(10) unsigned NOT NULL,
  `c81_token` varchar(64) NOT NULL COMMENT 'Hashed recovery token',
  `c81_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `c81_expires` timestamp NOT NULL,
  `c81_used` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `c81_used_at` timestamp NULL DEFAULT NULL,
  `c81_ip_address` varchar(45) DEFAULT NULL,
  `c81_user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`c81_id`),
  KEY `idx_user_id` (`c81_user_id`),
  KEY `idx_token` (`c81_token`),
  KEY `idx_expires` (`c81_expires`),
  CONSTRAINT `fk_pwd_recovery_user` FOREIGN KEY (`c81_user_id`) REFERENCES `t80_user` (`c80_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Password recovery tokens with 1-hour expiration';

-- Update version table
INSERT INTO `t00_version` (`c00_version`, `c00_date`)
VALUES ('12', current_timestamp())
ON DUPLICATE KEY UPDATE c00_date=current_timestamp();
