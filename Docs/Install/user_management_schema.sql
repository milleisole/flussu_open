-- ============================================================================
-- Flussu User Management System - Database Schema
-- ============================================================================
-- Sistema di gestione utenti per Flussu con supporto a 4 livelli gerarchici:
-- - Amministratore del sistema (role_id = 1)
-- - Editor di workflow (role_id = 2)
-- - Visualizzatore/Tester (role_id = 3)
-- - Utente finale (role_id = 0)
-- ============================================================================

-- Popolamento tabella ruoli
INSERT INTO `t90_role` (`c90_id`, `c90_name`, `c90_crud`) VALUES
(0, 'End User', 'R----'),           -- Solo lettura/esecuzione workflow
(1, 'System Admin', 'CRUDX'),       -- Tutti i permessi
(2, 'Workflow Editor', 'CRUD-'),    -- Crea, legge, modifica, elimina i propri workflow
(3, 'Viewer/Tester', 'R----')       -- Visualizza e testa workflow
ON DUPLICATE KEY UPDATE c90_name=VALUES(c90_name), c90_crud=VALUES(c90_crud);

-- Aggiornamento utente admin predefinito (ID 16)
UPDATE `t80_user`
SET c80_role = 1,
    c80_email = 'admin@example.com',
    c80_name = 'System',
    c80_surname = 'Administrator'
WHERE c80_id = 16;

-- Tabella per permessi granulari su workflow
DROP TABLE IF EXISTS `t88_wf_permissions`;
CREATE TABLE `t88_wf_permissions` (
  `c88_wf_id` int(10) unsigned NOT NULL COMMENT 'ID workflow',
  `c88_usr_id` int(10) unsigned NOT NULL COMMENT 'ID utente',
  `c88_permission` varchar(10) NOT NULL DEFAULT 'R' COMMENT 'Tipo permesso: R=Read, W=Write, X=Execute, D=Delete, O=Owner',
  `c88_granted_by` int(10) unsigned NOT NULL COMMENT 'ID utente che ha concesso il permesso',
  `c88_granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`c88_wf_id`, `c88_usr_id`),
  KEY `ix_usr` (`c88_usr_id`),
  KEY `ix_permission` (`c88_permission`),
  CONSTRAINT `fk_wf_perm_workflow` FOREIGN KEY (`c88_wf_id`) REFERENCES `t10_workflow` (`c10_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wf_perm_user` FOREIGN KEY (`c88_usr_id`) REFERENCES `t80_user` (`c80_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella per storico attività utenti (audit log)
DROP TABLE IF EXISTS `t92_user_audit`;
CREATE TABLE `t92_user_audit` (
  `c92_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `c92_usr_id` int(10) unsigned NOT NULL,
  `c92_action` varchar(50) NOT NULL COMMENT 'Tipo azione: login, logout, create_wf, edit_wf, delete_wf, ecc.',
  `c92_target_type` varchar(20) DEFAULT NULL COMMENT 'Tipo oggetto: workflow, user, project',
  `c92_target_id` int(10) unsigned DEFAULT NULL COMMENT 'ID oggetto target',
  `c92_ip_address` varchar(45) DEFAULT NULL,
  `c92_user_agent` varchar(255) DEFAULT NULL,
  `c92_details` text DEFAULT NULL COMMENT 'Dettagli aggiuntivi in JSON',
  `c92_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`c92_id`),
  KEY `ix_user` (`c92_usr_id`, `c92_timestamp`),
  KEY `ix_action` (`c92_action`),
  KEY `ix_timestamp` (`c92_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella per sessioni utente e API keys temporanei
DROP TABLE IF EXISTS `t94_user_sessions`;
CREATE TABLE `t94_user_sessions` (
  `c94_session_id` varchar(64) NOT NULL,
  `c94_usr_id` int(10) unsigned NOT NULL,
  `c94_api_key` varchar(128) DEFAULT NULL COMMENT 'API key temporaneo',
  `c94_ip_address` varchar(45) DEFAULT NULL,
  `c94_user_agent` varchar(255) DEFAULT NULL,
  `c94_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `c94_expires_at` timestamp NOT NULL,
  `c94_last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`c94_session_id`),
  KEY `ix_user` (`c94_usr_id`),
  KEY `ix_expires` (`c94_expires_at`),
  KEY `ix_api_key` (`c94_api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabella per inviti utente (registrazione con workflow)
DROP TABLE IF EXISTS `t96_user_invitations`;
CREATE TABLE `t96_user_invitations` (
  `c96_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `c96_email` varchar(65) NOT NULL,
  `c96_role` int(4) unsigned NOT NULL DEFAULT 0,
  `c96_invited_by` int(10) unsigned NOT NULL,
  `c96_invitation_code` varchar(64) NOT NULL,
  `c96_status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=accepted, 2=expired, 3=rejected',
  `c96_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `c96_expires_at` timestamp NOT NULL,
  `c96_accepted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`c96_id`),
  UNIQUE KEY `idx_code` (`c96_invitation_code`),
  KEY `idx_email` (`c96_email`),
  KEY `idx_status` (`c96_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vista per workflow con permessi utente
CREATE OR REPLACE VIEW `v25_wf_user_permissions` AS
SELECT
    w.c10_id AS wf_id,
    w.c10_wf_auid AS wf_auid,
    w.c10_name AS wf_name,
    w.c10_userid AS owner_id,
    u.c80_username AS owner_username,
    u.c80_email AS owner_email,
    COALESCE(p.c88_usr_id, w.c10_userid) AS user_id,
    COALESCE(p.c88_permission, 'O') AS permission,
    w.c10_active AS is_active
FROM t10_workflow w
LEFT JOIN t88_wf_permissions p ON p.c88_wf_id = w.c10_id
LEFT JOIN t80_user u ON u.c80_id = w.c10_userid
WHERE w.c10_deleted = '1899-12-31 23:59:59';

-- Vista per utenti con informazioni ruolo
CREATE OR REPLACE VIEW `v30_users_with_roles` AS
SELECT
    u.c80_id AS user_id,
    u.c80_username,
    u.c80_email,
    u.c80_name,
    u.c80_surname,
    u.c80_role AS role_id,
    r.c90_name AS role_name,
    r.c90_crud AS role_permissions,
    u.c80_created,
    u.c80_modified,
    CASE
        WHEN u.c80_deleted > '1899-12-31 23:59:59' THEN 0
        ELSE 1
    END AS is_active
FROM t80_user u
LEFT JOIN t90_role r ON r.c90_id = u.c80_role
WHERE u.c80_deleted = '1899-12-31 23:59:59';

-- ============================================================================
-- Fine schema gestione utenti
-- ============================================================================
