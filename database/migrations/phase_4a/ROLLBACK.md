# Phase 4A document-schema rollback guidance

Do not run rollback automatically. Back up both document tables first and disable future Phase 4A upload routes before changing their schema.

The conceptual rollback is:

```sql
ALTER TABLE `u676721746_essivery`.`partner_documents`
  DROP INDEX `uq_partner_document_active_type`,
  DROP INDEX `uq_partner_document_public`,
  DROP COLUMN `active_document_type`,
  DROP COLUMN `public_id`;

ALTER TABLE `u676721746_essivery`.`delivery_partner_documents`
  DROP INDEX `uq_delivery_document_active_type`,
  DROP INDEX `uq_delivery_document_public`,
  DROP COLUMN `active_document_type`,
  DROP COLUMN `public_id`;

DELETE FROM `u676721746_essivery`.`schema_migrations`
WHERE `version`='2026_08_31_4a_document_tables';
```

Dropping `public_id` after application deployment invalidates every safe document reference. Do not roll back after Phase 4A goes live without first disabling its API/UI and preserving a reviewed reference mapping.
