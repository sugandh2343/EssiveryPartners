-- REVIEW DRAFT ONLY — DO NOT EXECUTE before preflight review and backup.
-- Phase 3E normalized common weekly schedule, retaining legacy is_open.

ALTER TABLE `u676721746_essivery`.`partner_business_hours`
  ADD COLUMN IF NOT EXISTS `module_code` VARCHAR(30) NOT NULL DEFAULT 'RETAIL' AFTER `partner_id`,
  ADD COLUMN IF NOT EXISTS `slot_order` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `day_of_week`,
  ADD COLUMN IF NOT EXISTS `is_closed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_open`,
  ADD COLUMN IF NOT EXISTS `is_24_hours` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_closed`,
  ADD COLUMN IF NOT EXISTS `is_overnight` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_24_hours`,
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `closes_at`;

-- Backfill module scope from the existing canonical Partner-module mapping.
-- RETAIL remains the compatibility default when no unambiguous module exists.
UPDATE `u676721746_essivery`.`partner_business_hours` h
LEFT JOIN (
  SELECT partner_id,
         CASE
           WHEN SUM(module_code='HOME_SERVICE' AND status='active')>0 THEN 'HOME_SERVICE'
           WHEN SUM(module_code='RESTAURANT' AND status='active')>0 THEN 'RESTAURANT'
           ELSE 'RETAIL'
         END AS resolved_module
  FROM `u676721746_essivery`.`partner_business_modules`
  GROUP BY partner_id
) m ON m.partner_id=h.partner_id
SET h.module_code=COALESCE(m.resolved_module,'RETAIL')
WHERE h.module_code='RETAIL';

UPDATE `u676721746_essivery`.`partner_business_hours`
SET is_closed=CASE WHEN is_open=1 THEN 0 ELSE 1 END,
    is_overnight=CASE WHEN is_open=1 AND opens_at IS NOT NULL AND closes_at IS NOT NULL AND closes_at<opens_at THEN 1 ELSE 0 END,
    is_24_hours=0,
    opens_at=CASE WHEN is_open=1 THEN opens_at ELSE NULL END,
    closes_at=CASE WHEN is_open=1 THEN closes_at ELSE NULL END,
    slot_order=1,
    status='active';

-- Canonical Phase 17 index name. Preflight SHOW CREATE must confirm this name
-- before execution. If production uses a different equivalent index, stop.
ALTER TABLE `u676721746_essivery`.`partner_business_hours`
  DROP INDEX IF EXISTS `uq_partner_business_day`,
  ADD UNIQUE INDEX IF NOT EXISTS `uq_partner_hours_slot` (`partner_id`,`module_code`,`day_of_week`,`slot_order`),
  ADD INDEX IF NOT EXISTS `idx_partner_hours_active` (`partner_id`,`module_code`,`status`,`day_of_week`);

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
VALUES ('2026_08_30_3e_business_hours', 'Phase 3E normalized common weekly Partner schedules');
