-- Phase 4F Delivery Partner Setup. Additive and Delivery-owned.
CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`delivery_partner_vehicles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_partner_id` BIGINT UNSIGNED NOT NULL,
  `vehicle_type` VARCHAR(40) NOT NULL,
  `vehicle_make` VARCHAR(100) NULL,
  `vehicle_model` VARCHAR(100) NULL,
  `vehicle_number` VARCHAR(40) NULL,
  `vehicle_color` VARCHAR(40) NULL,
  `vehicle_year` SMALLINT UNSIGNED NULL,
  `registration_number` VARCHAR(80) NULL,
  `insurance_number` VARCHAR(100) NULL,
  `insurance_expiry` DATE NULL,
  `pollution_certificate_number` VARCHAR(100) NULL,
  `pollution_expiry` DATE NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_vehicles` (`delivery_partner_id`,`status`),
  CONSTRAINT `fk_delivery_vehicles_partner` FOREIGN KEY (`delivery_partner_id`) REFERENCES `u676721746_essivery`.`delivery_partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`delivery_vehicle_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `requires_registration` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_driving_licence` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_rc` TINYINT(1) NOT NULL DEFAULT 1,
  `insurance_required` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'inactive',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),UNIQUE KEY `uq_dvt_public` (`public_id`),UNIQUE KEY `uq_dvt_code` (`code`),KEY `idx_dvt_status_order` (`status`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `u676721746_essivery`.`delivery_vehicle_types`
(`public_id`,`code`,`name`,`requires_registration`,`requires_driving_licence`,`requires_rc`,`insurance_required`,`display_order`,`status`) VALUES
('DVT_00000000000000000000000000000001','bicycle','Bicycle',0,0,0,0,10,'active'),
('DVT_00000000000000000000000000000002','motorcycle','Motorcycle',1,1,1,0,20,'active'),
('DVT_00000000000000000000000000000003','scooter','Scooter',1,1,1,0,30,'active'),
('DVT_00000000000000000000000000000004','electric_scooter','Electric Scooter',1,1,1,0,40,'active'),
('DVT_00000000000000000000000000000005','car','Car',1,1,1,0,50,'active'),
('DVT_00000000000000000000000000000006','mini_truck','Mini Truck',1,1,1,0,60,'active'),
('DVT_00000000000000000000000000000007','other','Other Registered Vehicle',1,1,1,0,70,'active')
ON DUPLICATE KEY UPDATE name=VALUES(name),requires_registration=VALUES(requires_registration),requires_driving_licence=VALUES(requires_driving_licence),requires_rc=VALUES(requires_rc),insurance_required=VALUES(insurance_required),display_order=VALUES(display_order);

ALTER TABLE `u676721746_essivery`.`delivery_partner_vehicles`
  ADD COLUMN IF NOT EXISTS `vehicle_ownership` VARCHAR(30) NULL AFTER `registration_number`,
  ADD COLUMN IF NOT EXISTS `setup_confirmed_at` DATETIME(6) NULL AFTER `vehicle_ownership`,
  ADD COLUMN IF NOT EXISTS `current_vehicle_key` TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `is_primary`=1 AND `status`='active' THEN 1 ELSE NULL END) STORED AFTER `setup_confirmed_at`,
  ADD UNIQUE INDEX IF NOT EXISTS `uq_delivery_current_vehicle` (`delivery_partner_id`,`current_vehicle_key`);

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
VALUES ('2026_09_01_4f_delivery_setup','Phase 4F Delivery vehicle master and resumable current-vehicle setup fields');
