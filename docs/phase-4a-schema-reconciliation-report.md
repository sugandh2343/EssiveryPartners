# Essivery Partner Phase 4A — Documents schema reconciliation

Date: 2026-08-30  
Decision: **schema gate triggered; application implementation intentionally stopped**

## Existing canonical ownership

The authoritative schema already separates ownership correctly:

- Business identity documents: `partner_documents.partner_id → partners.id`
- Delivery identity documents: `delivery_partner_documents.delivery_partner_id → delivery_partners.id`

Both tables contain document type, internal file reference, review state/note, expiry, reviewer state, active status, and timestamps. This separation is compatible with PartnerContext and prevents forcing Delivery into the merchant owner model. The existing Admin Partner repository reads `partner_documents`; Delivery Admin uses its separate Delivery document model.

## Blocking gaps

The repository schema defines the two canonical tables, but production inspection showed that `partner_documents` is absent (and the revised preflight now diagnoses either table independently). When a legacy table exists, it lacks a non-numeric safe public document reference and active owner/type uniqueness. Returning numeric row IDs is forbidden, while using a raw `file_reference` as identity would expose storage details.

These are production-blocking security and consistency gaps. Application implementation therefore stopped under the prompt's schema-gate rule.

## Minimal migration

The revised migration creates either missing canonical table from the authoritative Essivery schema and safely augments either legacy table. It adds to each:

- `public_id CHAR(36)`, backfilled with server/database UUIDs and uniquely indexed;
- `active_document_type`, a stored generated value populated only for active rows;
- a unique owner + active-document-type index.

The generated-key approach permits inactive historical replacements while guaranteeing one canonical active document of each type. No existing file, review status, document number, owner, or approval data is rewritten. The migration creates no document rows.

The preflight must show no duplicate active owner/type groups and no existing `public_id` columns. If either condition fails, stop and reconcile that live state rather than running the migration.

## Document and storage decisions for the continuation

Planned canonical Phase 4A codes are `AADHAAR_FRONT`, `AADHAAR_BACK`, and `PAN`. No Aadhaar/PAN numbers will be collected. Review states will use the existing `pending`, `approved`, `rejected`, and `correction_required` conventions; pending/approved count as present, rejected/correction-required do not.

The existing generic upload helpers are intended for public catalogue/admin imagery and do not provide private identity-document serving. The application continuation must therefore implement authenticated Partner/Admin document streaming from a non-public configurable storage root. It should allow verified JPEG, PNG, WebP, and PDF files up to 5 MB, use generated filenames, and never return raw paths. This is application/storage work, not an additional schema migration.

## Package order

1. Run `database/migrations/phase_4a/000_preflight_documents.sql` (read-only).
2. Confirm both active-duplicate result sets are empty and both public-reference checks pass.
3. Take backups of both document tables.
4. Run `database/migrations/phase_4a/001_document_public_references.sql` once.
5. Run `database/verification/verify_phase_4a_documents.sql` (read-only).
6. Retain the verification output and confirm before application implementation.

No Phase 4A route, upload/storage service, repository, controller, setup-completion logic, frontend page, or test fixture was created because the specification requires operator reconciliation first.
