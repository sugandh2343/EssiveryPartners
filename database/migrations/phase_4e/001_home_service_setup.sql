-- Phase 4E Home Service Setup persistence.
-- Depends on the authoritative Phase 21A Home Services foundation.
ALTER TABLE `u676721746_essivery`.`home_service_provider_profiles`
  ADD COLUMN IF NOT EXISTS `setup_preference` VARCHAR(20) NULL AFTER `booking_buffer_minutes`,
  ADD COLUMN IF NOT EXISTS `setup_confirmed_at` DATETIME(6) NULL AFTER `setup_preference`;

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`home_service_provider_setup_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hspss_mapping` (`provider_id`,`service_id`),
  KEY `idx_hspss_service` (`service_id`),
  CONSTRAINT `fk_hspss_provider` FOREIGN KEY (`provider_id`) REFERENCES `u676721746_essivery`.`home_service_provider_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hspss_service` FOREIGN KEY (`service_id`) REFERENCES `u676721746_essivery`.`home_service_masters` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
VALUES ('2026_09_01_4e_home_service_setup','Phase 4E Home Service setup preference, confirmation, and draft service selections');
