# Phase 4B rollback guidance

Before application deployment, the new empty tables can be removed after backup confirmation:

```sql
DROP TABLE `u676721746_essivery`.`delivery_partner_bank_accounts`;
DROP TABLE `u676721746_essivery`.`partner_bank_accounts`;
DELETE FROM `u676721746_essivery`.`schema_migrations` WHERE `version`='2026_08_31_4b_secure_bank_accounts';
```

After Bank details are stored, rollback destroys encrypted payout destinations and invalidates setup readiness. Disable Phase 4B routes/UI and export encrypted rows before any reviewed rollback. Never export the encryption key with database backups.
