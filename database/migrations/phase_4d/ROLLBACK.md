# Phase 4D rollback

Prefer application rollback while retaining additive nullable columns/tables. Before destructive rollback, back up Restaurant cuisine selections.

```sql
ALTER TABLE `u676721746_essivery`.`restaurants` DROP INDEX IF EXISTS `uq_restaurants_owner_user`,DROP COLUMN IF EXISTS `setup_confirmed_at`,DROP COLUMN IF EXISTS `menu_setup_mode`;
DELETE FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4d_restaurant_setup';
```

Do not drop `restaurants` or `restaurant_cuisines`; both predate Phase 4D in production.
