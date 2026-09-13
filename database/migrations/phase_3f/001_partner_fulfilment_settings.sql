-- Phase 3F minimal additive Operations/Fulfilment persistence.
-- Run only after reviewing 000_preflight_operations.sql results and taking a backup.
-- This migration creates no Partner, module, fulfilment, or setup rows.

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`partner_fulfilment_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `partner_id` BIGINT UNSIGNED NOT NULL,
  `module_code` VARCHAR(30) NOT NULL,
  `essivery_delivery` TINYINT(1) NOT NULL DEFAULT 0,
  `self_delivery` TINYINT(1) NOT NULL DEFAULT 0,
  `customer_pickup` TINYINT(1) NOT NULL DEFAULT 0,
  `service_at_customer_location` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_fulfilment_public` (`public_id`),
  UNIQUE KEY `uq_partner_fulfilment_module` (`partner_id`,`module_code`),
  KEY `idx_partner_fulfilment_active` (`partner_id`,`module_code`,`status`),
  CONSTRAINT `fk_partner_fulfilment_partner`
    FOREIGN KEY (`partner_id`) REFERENCES `u676721746_essivery`.`partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
VALUES ('2026_08_30_3f_partner_fulfilment', 'Phase 3F module-scoped Partner fulfilment preferences');
