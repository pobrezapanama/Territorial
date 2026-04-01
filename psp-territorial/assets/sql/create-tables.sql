-- PSP Territorial - Create Tables
-- This file documents the schema used by the plugin.
-- The actual table creation is handled by class-psp-database.php on activation.

CREATE TABLE IF NOT EXISTS `{prefix}psp_territories` (
  `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)        NOT NULL,
  `slug`       VARCHAR(255)        NOT NULL,
  `type`       VARCHAR(20)         NOT NULL COMMENT 'provincia | distrito | corregimiento | comunidad',
  `parent_id`  BIGINT(20) UNSIGNED DEFAULT NULL,
  `metadata`   LONGTEXT            DEFAULT NULL COMMENT 'JSON extra data',
  `created_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_type`      (`type`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_slug`      (`slug`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
