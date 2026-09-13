-- Phase 4D canonical Restaurant profile extension and normalized cuisine mapping.
CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`restaurants` (
 `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`public_id` VARCHAR(36) NOT NULL,`owner_user_id` BIGINT UNSIGNED NULL,`name` VARCHAR(180) NOT NULL,`slug` VARCHAR(190) NOT NULL,`description` VARCHAR(1000) NULL,`logo_url` VARCHAR(500) NULL,`cover_image_url` VARCHAR(500) NULL,`city_id` BIGINT UNSIGNED NOT NULL,`pincode_id` BIGINT UNSIGNED NULL,`latitude` DECIMAL(10,7) NULL,`longitude` DECIMAL(10,7) NULL,`minimum_order` DECIMAL(12,2) NOT NULL DEFAULT 0,`delivery_fee` DECIMAL(12,2) NOT NULL DEFAULT 0,`operational_status` VARCHAR(30) NOT NULL DEFAULT 'closed',
 `status` VARCHAR(30) NOT NULL DEFAULT 'inactive',
 `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 `deleted_at` DATETIME(6) NULL,`menu_setup_mode` VARCHAR(20) NULL,`setup_confirmed_at` DATETIME(6) NULL,
 PRIMARY KEY(`id`),UNIQUE KEY `uq_restaurants_public`(`public_id`),UNIQUE KEY `uq_restaurants_slug`(`slug`),UNIQUE KEY `uq_restaurants_owner_user`(`owner_user_id`),CONSTRAINT `fk_restaurants_owner` FOREIGN KEY(`owner_user_id`) REFERENCES `u676721746_essivery`.`users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `u676721746_essivery`.`restaurants`
 ADD COLUMN IF NOT EXISTS `menu_setup_mode` VARCHAR(20) NULL,
 ADD COLUMN IF NOT EXISTS `setup_confirmed_at` DATETIME(6) NULL,
 ADD UNIQUE INDEX IF NOT EXISTS `uq_restaurants_owner_user`(`owner_user_id`);

CREATE TABLE IF NOT EXISTS `u676721746_essivery`.`restaurant_cuisines` (
 `restaurant_id` BIGINT UNSIGNED NOT NULL,`cuisine_id` BIGINT UNSIGNED NOT NULL,`status` VARCHAR(30) NOT NULL DEFAULT 'active',`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(`restaurant_id`,`cuisine_id`),KEY `fk_restaurant_cuisines_cuisine`(`cuisine_id`),
 CONSTRAINT `fk_restaurant_cuisine_restaurant` FOREIGN KEY(`restaurant_id`) REFERENCES `u676721746_essivery`.`restaurants`(`id`),CONSTRAINT `fk_restaurant_cuisine_master` FOREIGN KEY(`cuisine_id`) REFERENCES `u676721746_essivery`.`cuisines`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations`(`version`,`description`) VALUES('2026_09_01_4d_restaurant_setup','Phase 4D Restaurant confirmation, menu preference, and normalized cuisine mappings');
