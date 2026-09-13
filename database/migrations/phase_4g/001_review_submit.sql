-- Phase 4G application lifecycle history. Canonical application fields already exist from Phase 3A.
CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`partner_onboarding_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `application_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30) NULL,
  `to_status` VARCHAR(30) NOT NULL,
  `transition_type` VARCHAR(30) NOT NULL,
  `actor_type` VARCHAR(30) NOT NULL DEFAULT 'partner',
  `actor_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),UNIQUE KEY `uq_posh_public` (`public_id`),KEY `idx_posh_application` (`application_id`,`created_at`),
  CONSTRAINT `fk_posh_application` FOREIGN KEY (`application_id`) REFERENCES `u676721746_essivery`.`partner_onboarding_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
VALUES ('2026_09_01_4g_review_submit','Phase 4G Partner onboarding submission lifecycle history');

