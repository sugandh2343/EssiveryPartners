# Essivery Partner Phase 4A — Documents application implementation report

Date: 2026-08-30  
Status: **application complete locally; production deployment blocked pending Phase 4A SQL execution and verification**

## Target schema and ownership

Implementation targets the approved migration exactly:

- `partner_documents.public_id`
- `partner_documents.active_document_type`
- unique `partner_id + active_document_type`
- `delivery_partner_documents.public_id`
- `delivery_partner_documents.active_document_type`
- unique `delivery_partner_id + active_document_type`

Business documents remain owned by `partner_documents.partner_id → partners.id`. Delivery documents remain owned by `delivery_partner_documents.delivery_partner_id → delivery_partners.id`. PartnerContext exclusively chooses the server-side table and owner. No ownership field is accepted from a request.

## Backend delivery

Created:

- `api/partner/config/documents.php`
- `api/partner/Middleware/DocumentUploadRequestMiddleware.php`
- `api/partner/Repositories/PartnerDocumentRepository.php`
- `api/partner/Services/PartnerDocumentStorage.php`
- `api/partner/Services/PartnerDocumentsService.php`
- `api/partner/Controllers/PartnerDocumentsController.php`
- `api/partner/storage/private/.htaccess`

Modified:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/routes/protected.php`
- `api/partner/index.php`
- `api/partner/tests/run.php`

Routes:

- `GET /api/partner/setup/documents`
- `POST /api/partner/setup/documents` using multipart `documentType` + `file`
- `GET /api/partner/setup/documents/{publicDocumentId}/file`

All routes use the existing JWT, identity, rate-limit, and PartnerContext chain. The file route resolves only a new `DOC_...` reference or a migration-backfilled standard UUID owned by the current context, and never accepts a path or numeric ID.

## Upload and storage security

Supported canonical codes are exactly `AADHAAR_FRONT`, `AADHAAR_BACK`, and `PAN`. No identity numbers are collected.

Actual file content is inspected using `finfo`. Images must also pass `getimagesize`; PDFs must begin with the PDF signature. Allowed actual MIME types are JPEG, PNG, WebP, and PDF. Maximum size is 5 MB. Browser MIME, extension, and original filename are not trusted. Generated filenames use 48 cryptographically random hexadecimal characters and an extension derived from validated MIME.

Storage is centralized through `ESSIVERY_PARTNER_DOCUMENT_STORAGE`. The preferred production value is an absolute writable directory outside `public_html`. The Hostinger-compatible fallback is `api/partner/storage/private/documents`, protected by the supplied `.htaccess` and restrictive directory/file permissions. Storage is split by the owner public reference:

- `partners/{partner-public-id}/...`
- `delivery-partners/{delivery-public-id}/...`

Physical paths and internal file references are never returned. Streaming sets validated Content-Type, safe inline Content-Disposition, `X-Content-Type-Options: nosniff`, and `Cache-Control: private, no-store`.

## Replacement, history, and review

New content is validated and stored before the transaction. Inside the transaction, the old active row is retired and a new pending row is inserted. The approved generated-column unique key enforces one active owner/type. On database failure, the newly written file is removed. Inactive database/file history is retained for later Admin review rather than deleting the previous working evidence.

API review normalization:

- pending/under_review → `pending`, counts toward completion
- approved → `approved`, counts toward completion
- correction_required/needs_correction → `needs_correction`, unresolved
- rejected → `rejected`, unresolved

Replacement always returns to pending. A correction/rejection regresses the setup step and recalculates progress. Completing all three requirements marks Documents complete. Business progress is server-derived (normally 7/9 = 78%); Delivery uses its six-step definition (normally 4/6 = 67%). Bank resolves centrally as the next step.

Audit events are `partner_setup.document_uploaded`, `partner_setup.document_replaced`, `partner_setup.document_correction_resolved`, and `partner_setup.documents_completed`. Metadata includes only document type, MIME family, and normalized review state.

## Frontend delivery

Created:

- `frontend/src/pages/DocumentsPage.jsx`

Modified:

- `frontend/src/services/partner/partnerSetupService.js`
- `frontend/src/App.jsx`

The protected `/setup/documents` page displays the three requirements, immediate upload/replace controls, pending/approved/correction/rejected states, and resolved-count progress. It pre-checks the 5 MB/type constraints for UX while retaining server authority. Private files are fetched with the authenticated API client as Blobs; image object URLs are revoked on refresh/unmount and PDFs open from authenticated Blob URLs. Continue remains disabled until the server marks Documents complete and then follows backend next-step metadata.

## Tests and quality

The SQLite fixture models both approved target tables, public IDs, generated active keys, and unique active owner/type constraints.

- All Partner PHP files passed syntax checks.
- Partner automated suite: **260 assertions passed**.
- Temporary private-storage integration exercised PNG, JPEG, WebP and PDF acceptance; disguised PHP and oversize rejection; path-neutral generated filenames; replacement/history; correction regression; Business/Restaurant/Delivery isolation; safe public references; and MIME resolution.
- CLI tests simulate the final move with a copy because `move_uploaded_file` requires a real HTTP upload. Real production multipart movement is not claimed as tested.
- Frontend lint passed with only two pre-existing Fast Refresh warnings in Partner context files.
- Frontend production build passed.
- Build artifacts: `frontend/dist/index.html`, `frontend/dist/assets/index-yJ39XSBF.css`, `frontend/dist/assets/index-Ww7aHeqb.js`.

## Later deployment order and files

Production deployment is blocked until:

1. The revised phpMyAdmin-safe `database/migrations/phase_4a/000_preflight_documents.sql` is reviewed; it supports missing document tables.
2. Both active-duplicate results are empty and public-reference checks pass.
3. Both document tables are backed up.
4. `database/migrations/phase_4a/001_document_public_references.sql` executes once.
5. `database/verification/verify_phase_4a_documents.sql` passes.

After SQL verification, configure/create private storage and upload these PHP/runtime files:

1. `api/partner/config/documents.php`
2. `api/partner/Middleware/DocumentUploadRequestMiddleware.php`
3. `api/partner/Repositories/PartnerDocumentRepository.php`
4. `api/partner/Services/PartnerDocumentStorage.php`
5. `api/partner/Services/PartnerDocumentsService.php`
6. `api/partner/Controllers/PartnerDocumentsController.php`
7. `api/partner/storage/private/.htaccess`
8. `api/partner/Services/PartnerSetupService.php`
9. `api/partner/routes/protected.php`
10. `api/partner/index.php`

Deploy the complete contents of `frontend/dist/` together. Production PHP must have `fileinfo` enabled and `upload_max_filesize` greater than 5 MB; set `post_max_size` comfortably above 5 MB (recommended at least 6 MB). The PHP user needs create/read/write permission on the configured private root; never grant public HTTP access.

## Remaining risks and readiness

Production multipart upload, Apache deny-rule enforcement, filesystem permissions, SQL compatibility/results, and authenticated streaming remain unverified until Hostinger is available. Admin can continue reading canonical rows; a future Admin authenticated file-serving integration will be needed to preview private evidence without exposing storage.

The Phase 4A code is ready to deploy immediately after SQL verification and private-storage configuration. From a local development perspective, Phase 4A is complete and Phase 4B Bank & Payout Details can begin; production activation remains blocked by the explicit SQL/storage checklist above.
