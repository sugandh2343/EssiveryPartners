-- Phase 4A resumable canonical tables. Creates no document rows.
-- Prerequisites: partners and delivery_partners exist; duplicate preflight results are empty.
CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`partner_documents` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`public_id` CHAR(36) NULL,`partner_id` BIGINT UNSIGNED NOT NULL,
 `document_type` VARCHAR(60) NOT NULL,`active_document_type` VARCHAR(60) GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN `document_type` ELSE NULL END) STORED,
 `document_number_encrypted` VARBINARY(512) NULL,`document_number_last4` CHAR(4) NULL,`file_reference` VARCHAR(500) NOT NULL,
 `review_status` VARCHAR(30) NOT NULL DEFAULT 'pending',`review_note` VARCHAR(500) NULL,`expiry_date` DATE NULL,`reviewed_by` BIGINT UNSIGNED NULL,`reviewed_at` DATETIME(6) NULL,
 `status` VARCHAR(20) NOT NULL DEFAULT 'active',`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(`id`),KEY `idx_partner_documents`(`partner_id`,`document_type`,`review_status`),CONSTRAINT `fk_partner_documents_partner` FOREIGN KEY(`partner_id`) REFERENCES `u676721746_essivery`.`partners`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `u676721746_essivery`.`partner_documents`
 ADD COLUMN IF NOT EXISTS `public_id` CHAR(36) NULL AFTER `id`,
 ADD COLUMN IF NOT EXISTS `active_document_type` VARCHAR(60) GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN `document_type` ELSE NULL END) STORED AFTER `document_type`;
UPDATE `u676721746_essivery`.`partner_documents` SET `public_id`=UUID() WHERE `public_id` IS NULL;
ALTER TABLE `u676721746_essivery`.`partner_documents` MODIFY `public_id` CHAR(36) NOT NULL,
 ADD UNIQUE INDEX IF NOT EXISTS `uq_partner_document_public`(`public_id`),ADD UNIQUE INDEX IF NOT EXISTS `uq_partner_document_active_type`(`partner_id`,`active_document_type`);

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`delivery_partner_documents` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`public_id` CHAR(36) NULL,`delivery_partner_id` BIGINT UNSIGNED NOT NULL,
 `document_type` VARCHAR(60) NOT NULL,`active_document_type` VARCHAR(60) GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN `document_type` ELSE NULL END) STORED,
 `document_number_encrypted` VARBINARY(512) NULL,`document_number_last4` CHAR(4) NULL,`file_reference` VARCHAR(500) NOT NULL,
 `review_status` VARCHAR(40) NOT NULL DEFAULT 'pending',`review_note` VARCHAR(500) NULL,`expiry_date` DATE NULL,`reviewed_by` BIGINT UNSIGNED NULL,`reviewed_at` DATETIME(6) NULL,
 `status` VARCHAR(20) NOT NULL DEFAULT 'active',`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(`id`),KEY `idx_delivery_documents`(`delivery_partner_id`,`document_type`,`review_status`),CONSTRAINT `fk_delivery_documents_partner` FOREIGN KEY(`delivery_partner_id`) REFERENCES `u676721746_essivery`.`delivery_partners`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `u676721746_essivery`.`delivery_partner_documents`
 ADD COLUMN IF NOT EXISTS `public_id` CHAR(36) NULL AFTER `id`,
 ADD COLUMN IF NOT EXISTS `active_document_type` VARCHAR(60) GENERATED ALWAYS AS(CASE WHEN `status`='active' THEN `document_type` ELSE NULL END) STORED AFTER `document_type`;
UPDATE `u676721746_essivery`.`delivery_partner_documents` SET `public_id`=UUID() WHERE `public_id` IS NULL;
ALTER TABLE `u676721746_essivery`.`delivery_partner_documents` MODIFY `public_id` CHAR(36) NOT NULL,
 ADD UNIQUE INDEX IF NOT EXISTS `uq_delivery_document_public`(`public_id`),ADD UNIQUE INDEX IF NOT EXISTS `uq_delivery_document_active_type`(`delivery_partner_id`,`active_document_type`);

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations`(`version`,`description`)
VALUES('2026_08_31_4a_document_tables','Phase 4A canonical Business/Delivery document tables and safe active references');
