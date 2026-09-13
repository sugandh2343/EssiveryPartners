# Phase 3E Business Hours rollback guidance

This migration changes schedule semantics and index cardinality. Do not run rollback automatically.

Rollback is safe only before the Phase 3E application writes multiple slots, explicit overnight schedules, or 24-hour schedules. Back up the table first and confirm there is at most one active row per `partner_id,day_of_week`; otherwise restoring the legacy unique index will fail and discarding rows would lose data.

After that confirmation, the conceptual rollback is:

```sql
ALTER TABLE `u676721746_essivery`.`partner_business_hours`
  DROP INDEX `uq_partner_hours_slot`,
  DROP INDEX `idx_partner_hours_active`,
  ADD UNIQUE INDEX `uq_partner_business_day` (`partner_id`,`day_of_week`),
  DROP COLUMN `status`,
  DROP COLUMN `is_overnight`,
  DROP COLUMN `is_24_hours`,
  DROP COLUMN `is_closed`,
  DROP COLUMN `slot_order`,
  DROP COLUMN `module_code`;

DELETE FROM `u676721746_essivery`.`schema_migrations`
WHERE `version`='2026_08_30_3e_business_hours';
```

Do not rollback after new schedule data exists without a reviewed conversion/export plan.

