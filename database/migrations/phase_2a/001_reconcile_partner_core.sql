-- Prompt 2A production schema reconciliation.
-- Canonical sources:
--   Essivery Admin/database/essivery_full_schema_mysql8.sql
--   api/partner/Repositories/PartnerContextRepository.php
--
-- This migration is additive and idempotent. It creates no identities, Partner
-- records, delivery records, module assignments, or marketplace configuration.
-- Run database/verification/verify_phase_2a_partner_core_preflight.sql first.

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS partners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(180) NOT NULL,
  owner_name VARCHAR(150) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  email VARCHAR(190) NULL,
  business_type VARCHAR(50) NULL,
  registration_number VARCHAR(100) NULL,
  tax_number VARCHAR(100) NULL,
  address_line_1 VARCHAR(255) NULL,
  address_line_2 VARCHAR(255) NULL,
  landmark VARCHAR(150) NULL,
  city_id BIGINT UNSIGNED NULL,
  state_id BIGINT UNSIGNED NULL,
  pincode VARCHAR(12) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  onboarding_status VARCHAR(40) NOT NULL DEFAULT 'draft',
  approval_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partners_public_id (public_id),
  UNIQUE KEY uq_partners_user (user_id),
  KEY idx_partners_review (approval_status, onboarding_status),
  CONSTRAINT fk_partners_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_partners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  mobile VARCHAR(20) NOT NULL,
  date_of_birth DATE NULL,
  gender VARCHAR(30) NULL,
  address_line VARCHAR(255) NULL,
  landmark VARCHAR(150) NULL,
  city_id BIGINT UNSIGNED NULL,
  state_id BIGINT UNSIGNED NULL,
  pincode VARCHAR(12) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  emergency_contact_name VARCHAR(150) NULL,
  emergency_contact_mobile VARCHAR(20) NULL,
  onboarding_status VARCHAR(40) NOT NULL DEFAULT 'draft',
  approval_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  availability_status VARCHAR(40) NOT NULL DEFAULT 'offline',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  deleted_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_partners_public_id (public_id),
  UNIQUE KEY uq_delivery_partners_user (user_id),
  KEY idx_delivery_partners_status (approval_status, availability_status, status),
  CONSTRAINT fk_delivery_partners_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

