# Phase 3D Location-column rollback guidance

This migration adds nullable schema fields and does not rewrite existing rows. Do not automatically execute this rollback.

Rollback is acceptable only before the Partner application begins saving Location data into these columns. Once production data uses them, first disable Location writes, export the affected columns, and prepare an explicit data-preservation plan.

After confirming rollback is safe, run:

```sql
USE `u676721746_essivery`;

ALTER TABLE `partners`
  DROP COLUMN `locality`;

ALTER TABLE `delivery_partners`
  DROP COLUMN `locality`,
  DROP COLUMN `address_line_2`;

DELETE FROM `schema_migrations`
WHERE `version` = '2026_08_30_3d_location_columns';
```

The rollback statements are intentionally not idempotent: inspect the live schema and take a backup before destructive DDL. Never run them after Location application code has started storing production values without first preserving that data.

