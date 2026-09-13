# Phase 4E rollback

Phase 4E is additive. Before rollback, export `home_service_provider_setup_services` and the two setup columns from `home_service_provider_profiles`.

```sql
DELETE FROM schema_migrations WHERE version='2026_09_01_4e_home_service_setup';
DROP TABLE IF EXISTS home_service_provider_setup_services;
ALTER TABLE home_service_provider_profiles
  DROP COLUMN IF EXISTS setup_confirmed_at,
  DROP COLUMN IF EXISTS setup_preference;
```

This rollback removes saved Phase 4E selections and confirmations. It does not alter the Phase 21A taxonomy, provider-service pricing mappings, marketplace state, bookings, or provider approval state.

