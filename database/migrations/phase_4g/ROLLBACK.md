# Phase 4G rollback

Back up `partner_onboarding_applications`, `partners`, `delivery_partners`, and `partner_onboarding_status_history` first.

```sql
DELETE FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4g_review_submit';
DROP TABLE IF EXISTS `u676721746_essivery`.`partner_onboarding_status_history`;
```

This removes lifecycle history only. It deliberately does not reverse already-submitted application or coarse onboarding states automatically; those require owner-by-owner operational review.

