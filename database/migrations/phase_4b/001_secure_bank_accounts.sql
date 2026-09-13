-- Phase 4B secure current/historical Bank destinations. Creates no account rows.
-- Application encryption: AES-256-GCM using PARTNER_BANK_ENCRYPTION_KEY (minimum 32 characters).
CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`partner_bank_accounts` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`public_id` CHAR(36) NOT NULL,`partner_id` BIGINT UNSIGNED NOT NULL,
 `account_holder_name` VARCHAR(150) NOT NULL,`account_number_encrypted` VARBINARY(512) NOT NULL,`account_number_last4` CHAR(4) NOT NULL,
 `encryption_version` TINYINT UNSIGNED NOT NULL DEFAULT 1,`ifsc_code` CHAR(11) NOT NULL,
 `review_status` VARCHAR(30) NOT NULL DEFAULT 'pending',`review_note` VARCHAR(500) NULL,`reviewed_by` BIGINT UNSIGNED NULL,`reviewed_at` DATETIME(6) NULL,
 `status` VARCHAR(20) NOT NULL DEFAULT 'active',`active_account_key` TINYINT UNSIGNED GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN 1 ELSE NULL END) STORED,
 `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(`id`),UNIQUE KEY `uq_partner_bank_public`(`public_id`),UNIQUE KEY `uq_partner_bank_active`(`partner_id`,`active_account_key`),
 KEY `idx_partner_bank_review`(`partner_id`,`review_status`,`status`),CONSTRAINT `fk_partner_bank_partner` FOREIGN KEY(`partner_id`) REFERENCES `u676721746_essivery`.`partners`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`delivery_partner_bank_accounts` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`public_id` CHAR(36) NOT NULL,`delivery_partner_id` BIGINT UNSIGNED NOT NULL,
 `account_holder_name` VARCHAR(150) NOT NULL,`account_number_encrypted` VARBINARY(512) NOT NULL,`account_number_last4` CHAR(4) NOT NULL,
 `encryption_version` TINYINT UNSIGNED NOT NULL DEFAULT 1,`ifsc_code` CHAR(11) NOT NULL,
 `review_status` VARCHAR(30) NOT NULL DEFAULT 'pending',`review_note` VARCHAR(500) NULL,`reviewed_by` BIGINT UNSIGNED NULL,`reviewed_at` DATETIME(6) NULL,
 `status` VARCHAR(20) NOT NULL DEFAULT 'active',`active_account_key` TINYINT UNSIGNED GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN 1 ELSE NULL END) STORED,
 `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(`id`),UNIQUE KEY `uq_delivery_bank_public`(`public_id`),UNIQUE KEY `uq_delivery_bank_active`(`delivery_partner_id`,`active_account_key`),
 KEY `idx_delivery_bank_review`(`delivery_partner_id`,`review_status`,`status`),CONSTRAINT `fk_delivery_bank_partner` FOREIGN KEY(`delivery_partner_id`) REFERENCES `u676721746_essivery`.`delivery_partners`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations`(`version`,`description`)
VALUES('2026_08_31_4b_secure_bank_accounts','Phase 4B encrypted Business and Delivery current payout destinations');
