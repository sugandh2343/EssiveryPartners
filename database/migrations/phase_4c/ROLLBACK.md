# Phase 4C rollback guidance

Take and verify a database backup before deployment. Application rollback is preferred: remove the Phase 4C PHP/frontend release while leaving the two nullable columns in place; they are backward compatible.

If schema rollback is explicitly approved after confirming no Phase 4C values are needed:

```sql
ALTER TABLE `u676721746_essivery`.`partner_retail_parent_categories`
  DROP INDEX IF EXISTS `uq_partner_retail_category`,
  DROP COLUMN IF EXISTS `retail_confirmed_at`,
  DROP COLUMN IF EXISTS `catalogue_setup_mode`;
DELETE FROM `u676721746_essivery`.`schema_migrations` WHERE `version`='2026_08_31_4c_retail_setup';
```

This does not delete Retail mappings, Partners, categories, products, inventory, or setup applications.
