# Prompt 2A production schema reconciliation

## Decision

`partners` and `delivery_partners` are the two canonical ownership cores. Their
authoritative definitions exist in the Essivery Admin full schema, but Prompt 2A
did not include a deployable migration. The Partner API intentionally requires a
unique `user_id` in each core and checks both cores to reject ambiguous ownership.

The repair migration creates either table only when it is missing. It does not
alter `users`, `user_identities`, Firebase authentication, existing tables, or
module-specific configuration. It inserts no rows.

The city/state columns are retained for Admin compatibility, but their optional
foreign keys are intentionally not added by this core repair: production presence
of `cities` and `states` has not been established, and Prompt 2A does not require
those relationships. The required ownership foreign key to `users(id)` is kept.

## Execution

1. Back up the database schema and data.
2. Run `database/verification/verify_phase_2a_partner_core_preflight.sql`.
3. Stop if any summarized preflight check reports `FAIL`.
4. Run `database/migrations/phase_2a/001_reconcile_partner_core.sql`.
5. Run `database/verification/verify_phase_2a_partner_core.sql`.
6. Confirm both row counts are zero before exercising bootstrap.
7. Smoke-test Grocery bootstrap and context, then test a Delivery identity if one
   is available. Grocery must create only `partners`; Delivery must create only
   `delivery_partners`.

## Rollback

Before bootstrap creates any row, rollback is:

```sql
DROP TABLE IF EXISTS delivery_partners;
DROP TABLE IF EXISTS partners;
```

Only drop a table that the migration created, and only after verifying it remains
empty and has no inbound foreign keys. Once application records exist, do not drop
either table; restore from backup or perform a reviewed data migration instead.

