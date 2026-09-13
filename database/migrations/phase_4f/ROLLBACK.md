# Phase 4F rollback

Back up `delivery_partner_vehicles` and `delivery_vehicle_types` first. Then, only if Phase 4F must be reverted:

```sql
DELETE FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4f_delivery_setup';
ALTER TABLE `u676721746_essivery`.`delivery_partner_vehicles`
  DROP INDEX IF EXISTS `uq_delivery_current_vehicle`,
  DROP COLUMN IF EXISTS `current_vehicle_key`,
  DROP COLUMN IF EXISTS `setup_confirmed_at`,
  DROP COLUMN IF EXISTS `vehicle_ownership`;
DROP TABLE IF EXISTS `u676721746_essivery`.`delivery_vehicle_types`;
```

This does not remove Delivery Partners, vehicles, documents, assignments, jobs, wallet entries, or approvals. It removes Phase 4F confirmation/ownership data and the normalized option master.

