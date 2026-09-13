-- Prompt 3A: resumable Partner Business Setup persistence.
-- Preconditions: canonical partners, delivery_partners, users, and audit_logs.
-- This migration stores workflow state only; authoritative business field values remain in domain tables.
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS partner_onboarding_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  business_partner_id BIGINT UNSIGNED NULL,
  delivery_partner_id BIGINT UNSIGNED NULL,
  identity_type VARCHAR(50) NOT NULL,
  module_code VARCHAR(30) NOT NULL,
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  application_status VARCHAR(30) NOT NULL DEFAULT 'draft',
  current_step VARCHAR(60) NULL,
  completion_percentage TINYINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME(6) NULL,
  last_saved_at DATETIME(6) NULL,
  is_current TINYINT(1) NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_partner_setup_public (public_id),
  UNIQUE KEY uq_partner_setup_business_current (business_partner_id, is_current),
  UNIQUE KEY uq_partner_setup_delivery_current (delivery_partner_id, is_current),
  KEY idx_partner_setup_status (application_status, status, updated_at),
  CONSTRAINT fk_partner_setup_business FOREIGN KEY (business_partner_id) REFERENCES partners(id),
  CONSTRAINT fk_partner_setup_delivery FOREIGN KEY (delivery_partner_id) REFERENCES delivery_partners(id),
  CONSTRAINT chk_partner_setup_owner CHECK (
    (business_partner_id IS NOT NULL AND delivery_partner_id IS NULL) OR
    (business_partner_id IS NULL AND delivery_partner_id IS NOT NULL)
  ),
  CONSTRAINT chk_partner_setup_percentage CHECK (completion_percentage BETWEEN 0 AND 100),
  CONSTRAINT chk_partner_setup_current CHECK (is_current IS NULL OR is_current = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_onboarding_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(36) NOT NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  step_code VARCHAR(60) NOT NULL,
  requirement_type VARCHAR(20) NOT NULL DEFAULT 'required',
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  step_status VARCHAR(30) NOT NULL DEFAULT 'not_started',
  completed_at DATETIME(6) NULL,
  last_saved_at DATETIME(6) NULL,
  revision_no INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_partner_setup_step_public (public_id),
  UNIQUE KEY uq_partner_setup_step (application_id, step_code),
  KEY idx_partner_setup_step_status (application_id, step_status, is_required),
  CONSTRAINT fk_partner_setup_step_application FOREIGN KEY (application_id)
    REFERENCES partner_onboarding_applications(id) ON DELETE CASCADE,
  CONSTRAINT chk_partner_setup_step_requirement CHECK (requirement_type IN ('required','optional','conditional')),
  CONSTRAINT chk_partner_setup_step_status CHECK (step_status IN ('not_started','in_progress','complete','needs_correction'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version, description)
VALUES ('2026_08_28_3001', 'Partner Business Setup applications and resumable step state');
