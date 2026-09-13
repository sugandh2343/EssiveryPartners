-- Phase 4C minimal additive extension of the canonical Retail-category association.
-- Preconditions: preflight passed, no duplicate partner/category rows, and backup completed.
ALTER TABLE `u676721746_essivery`.`partner_retail_parent_categories`
 ADD COLUMN IF NOT EXISTS `catalogue_setup_mode` VARCHAR(20) NULL AFTER `parent_category_id`,
 ADD COLUMN IF NOT EXISTS `retail_confirmed_at` DATETIME(6) NULL AFTER `catalogue_setup_mode`,
 ADD UNIQUE INDEX IF NOT EXISTS `uq_partner_retail_category` (`partner_id`,`parent_category_id`);

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations`(`version`,`description`)
VALUES('2026_08_31_4c_retail_setup','Phase 4C Retail confirmation and catalogue onboarding preference on canonical category mappings');
