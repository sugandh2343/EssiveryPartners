# Phase 3F Operations/Fulfilment rollback guidance

Do not run rollback automatically. Back up `partner_fulfilment_settings` first.

The Phase 3F table is additive and has no pre-migration source rows. Before the Phase 3F application is deployed, rollback removes only the empty new table and its migration marker:

```sql
DROP TABLE `u676721746_essivery`.`partner_fulfilment_settings`;

DELETE FROM `u676721746_essivery`.`schema_migrations`
WHERE `version`='2026_08_30_3f_partner_fulfilment';
```

After the Phase 3F application has stored preferences, dropping the table permanently deletes Partner fulfilment choices. Export the table and disable the Phase 3F routes/UI before any reviewed rollback.
