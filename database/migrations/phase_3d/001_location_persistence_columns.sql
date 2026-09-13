-- Phase 3D Location persistence reconciliation.
-- Additive, schema-only, MariaDB-safe, and safe to rerun.
-- No existing row values are modified.

ALTER TABLE `u676721746_essivery`.`partners`
  ADD COLUMN IF NOT EXISTS `locality` VARCHAR(150) NULL AFTER `address_line_2`;

ALTER TABLE `u676721746_essivery`.`delivery_partners`
  ADD COLUMN IF NOT EXISTS `address_line_2` VARCHAR(255) NULL AFTER `address_line`;

ALTER TABLE `u676721746_essivery`.`delivery_partners`
  ADD COLUMN IF NOT EXISTS `locality` VARCHAR(150) NULL AFTER `address_line_2`;

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`, `description`)
VALUES ('2026_08_30_3d_location_columns', 'Phase 3D canonical Partner and Delivery location columns');

