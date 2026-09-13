# Prompt 3A rollback guidance

Application rollback is safe before Partners begin saving future setup forms because these tables contain workflow state only.

1. Disable Partner setup writes and deploy the pre-3A frontend/API.
2. Export `partner_onboarding_applications` and `partner_onboarding_steps` for recovery.
3. Confirm no downstream Admin review or Prompt 3B code references these tables.
4. Drop the child table first, then the parent:

```sql
DROP TABLE partner_onboarding_steps;
DROP TABLE partner_onboarding_applications;
DELETE FROM schema_migrations WHERE version = '2026_08_28_3001';
```

Do not run this rollback after later setup phases start persisting review-critical workflow without a replacement migration and data conversion plan.
