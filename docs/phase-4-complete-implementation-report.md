# Essivery Partner Platform — Complete Phase 4 Report

## Phase 4A through Phase 4G

Report date: 2026-09-02  
Scope: Documents, Bank Details, Retail Setup, Restaurant Setup, Home Service Setup, Delivery Setup, Review/Submit, correction compatibility, and lifecycle locking.

## 1. Executive status

Phase 4 application development is complete locally from 4A through 4G. After the 2026-09-02 Lucknow taxonomy/map diagnostic update, the combined codebase passes **470 backend assertions**, frontend lint with no errors, and a production frontend build.

Phase 4 completes Partner onboarding data collection and submission. It deliberately does **not** implement Admin approval decisions, marketplace publication, catalogue/menu management, service pricing, orders, bookings, delivery jobs, wallets, referral rewards, settlements, or payouts.

The final onboarding model is:

1. Business Partners complete nine required setup steps.
2. Delivery Partners complete six required setup steps.
3. Reaching 100% means the application is ready to submit; it does not mean submitted or approved.
4. Review & Submit changes application lifecycle only.
5. Submitted and under-review applications are read-only.
6. When Admin requests corrections, only the requested sections become editable.
7. Resubmission reuses the same canonical application and preserves its original submission timestamp.

## 2. Phase completion matrix

| Phase | Scope | Canonical persistence | API/UI result | Final cumulative assertions at phase completion |
|---|---|---|---|---:|
| 4A | Documents & Verification | `partner_documents`, `delivery_partner_documents`, private file storage | Authenticated upload, replacement, correction state, private preview | 260 |
| 4B | Bank & Payout Details | `partner_bank_accounts`, `delivery_partner_bank_accounts` | Encrypted account storage, masked GET, replace/history workflow | 298 |
| 4C | Retail Store Setup | `partner_business_modules`, `partner_retail_parent_categories` | Category-derived Retail confirmation and catalogue preference | 344 |
| 4D | Restaurant Setup | `restaurants`, `restaurant_cuisines`, `cuisines` | Cuisine selection, menu preference, confirmation | 373 |
| 4E | Home Service Setup | `home_service_provider_profiles`, `home_service_provider_setup_services` | Draft service selection, setup preference, confirmation | 394 |
| 4F | Delivery Partner Setup | `delivery_partners`, `delivery_partner_vehicles`, `delivery_vehicle_types` | Vehicle setup, dynamic document requirements, confirmation | 425 |
| 4G | Review, Submit & Completion | `partner_onboarding_applications`, `partner_onboarding_steps`, `partner_onboarding_status_history` | Consolidated safe review, submission, correction/resubmission, read-only lifecycle | 469 |

Post-Phase-4 location regression coverage adds one assertion for the confirmed `226028 / Lucknow / Uttar Pradesh` taxonomy path, bringing the current total to **470**.

## 3. Shared architecture and ownership

- Authentication and identity continue through the established JWT and Partner context chain.
- The client cannot provide Partner, Delivery Partner, User, application, category, module, or row ownership IDs.
- Business ownership is derived from authenticated `partners.id`.
- Delivery ownership is derived independently from authenticated `delivery_partners.id`.
- Same-mobile Business and Delivery identities remain separate because ownership is based on the selected authenticated identity, not mobile number.
- `partner_onboarding_applications` is the canonical lifecycle record.
- `partner_onboarding_steps` remains the canonical progress/requirement record.
- Business retains nine required steps; Delivery retains six.
- All completion percentages are calculated on the server.
- Setup writes use strict request allowlists and the established idempotency infrastructure.
- Phase 4G adds a central lifecycle mutation guard to all setup write routes.

## 4. Phase 4A — Documents & Verification

### Delivered behavior

- Supports `AADHAAR_FRONT`, `AADHAAR_BACK`, and `PAN` without collecting identity numbers.
- Delivery identities additionally use vehicle-document types introduced in 4F.
- Files are inspected by actual content using `finfo`; images also pass image validation and PDFs require a valid signature.
- Allowed types: JPEG, PNG, WebP, and PDF.
- Maximum size: 5 MB.
- Random server filenames are generated independently of the browser filename.
- Files are stored through `ESSIVERY_PARTNER_DOCUMENT_STORAGE`, preferably outside `public_html`.
- The Hostinger-compatible fallback is protected by `api/partner/storage/private/.htaccess`.
- Replacement retires the previous active row while preserving history.
- Correction/rejection regresses the setup step; replacement returns the document to pending.
- Private files are streamed only through an authenticated, owner-scoped, opaque-reference endpoint.

### API

- `GET /api/partner/setup/documents`
- `POST /api/partner/setup/documents`
- `GET /api/partner/setup/documents/{publicDocumentId}/file`

### Schema package

- `database/migrations/phase_4a/000_preflight_documents.sql`
- `database/migrations/phase_4a/001_document_public_references.sql`
- `database/verification/verify_phase_4a_documents.sql`
- Marker: `2026_08_31_4a_document_tables`

### Security boundaries

No physical path, internal file reference, numeric ID, Aadhaar number, PAN number, or internal review actor is exposed. Streaming uses `nosniff`, private/no-store caching, validated content type, and safe inline disposition.

## 5. Phase 4B — Bank & Payout Details

### Delivered behavior

- Separate canonical Business and Delivery bank tables.
- AES-256-GCM encryption through PHP OpenSSL.
- Random 12-byte IV and 16-byte authentication tag for each encryption.
- Versioned binary envelope; current encryption version is 1.
- GET masks from the stored last four digits and never decrypts merely to render a mask.
- Account-holder name, account number, confirmation, and IFSC receive server validation.
- A material change retires the old active record and creates a new pending record.
- Identical ordinary saves do not create unnecessary history.
- Correction/rejected bank state regresses setup; a corrected replacement returns to pending.

### Required production configuration

- `PARTNER_BANK_ENCRYPTION_KEY`: strong protected secret, at least 32 characters, with no committed/default value.
- PHP OpenSSL must be enabled.
- Upstream request-body logging must redact `accountNumber` and `confirmAccountNumber`.

### API

- `GET /api/partner/setup/bank`
- `PUT /api/partner/setup/bank`

### Schema package

- `database/migrations/phase_4b/000_preflight_bank_accounts.sql`
- `database/migrations/phase_4b/001_secure_bank_accounts.sql`
- `database/verification/verify_phase_4b_bank_accounts.sql`
- Marker: `2026_08_31_4b_secure_bank_accounts`

### Security boundaries

Responses contain only safe bank presence, holder, masked account, last four, IFSC, normalized review status, and timestamps. Full account number, confirmation, ciphertext, IV, authentication tag, encryption key, and envelope internals are never returned or audited.

## 6. Phase 4C — Retail Store Setup

### Delivered behavior

- Supports Grocery, Fresh Fruits and Vegetables, Pharmacy, Fashion, and Electronics Retail identities.
- Category is derived from authenticated Partner context and cannot be selected through the request.
- Uses existing Retail module and Partner/category mapping rather than inventing another Retail owner table.
- Persists catalogue setup preference as `SELF` or `ASSISTED`.
- Requires explicit confirmation.
- Completion produces the ninth Business step and may bring the application to 100%.

### API

- `GET /api/partner/setup/retail`
- `PUT /api/partner/setup/retail`

### Schema package

- `database/migrations/phase_4c/000_preflight_retail_setup.sql`
- `database/migrations/phase_4c/001_retail_setup.sql`
- `database/verification/verify_phase_4c_retail_setup.sql`
- Marker: `2026_08_31_4c_retail_setup`

### Deferred boundaries

No products, master-product assignment, inventory, stock, prices, catalogue publication, minimum order, delivery radius, marketplace visibility, or store activation is created.

## 7. Phase 4D — Restaurant Setup

### Delivered behavior

- Reconciled to production ownership through `restaurants.owner_user_id`, not a guessed `partner_id`.
- Uses the existing active `cuisines` master and normalized `restaurant_cuisines` mappings.
- Requires 1–5 unique active cuisine codes.
- Persists `SELF` or `ASSISTED` menu setup preference and explicit confirmation.
- A missing canonical Restaurant profile can be created deterministically from the authenticated Business identity while remaining inactive and closed.
- Existing profiles/mappings are resumed rather than duplicated.

### API

- `GET /api/partner/setup/restaurant`
- `PUT /api/partner/setup/restaurant`

### Schema package

- `database/migrations/phase_4d/000_preflight_restaurant_setup.sql`
- `database/migrations/phase_4d/001_restaurant_setup.sql`
- `database/verification/verify_phase_4d_restaurant_setup.sql`
- Marker: `2026_09_01_4d_restaurant_setup`

### Deferred boundaries

No guessed Restaurant type or VEG/NON-VEG model was introduced. FSSAI, menu items, dishes, categories, prices, variants, addons, inventory, orders, kitchen state, booking, approval, and marketplace activation remain outside Phase 4.

## 8. Phase 4E — Home Service Setup

### Delivered behavior

- Canonical owner is `home_service_provider_profiles.partner_id`.
- Uses active Home Service category/subcategory/service masters.
- Introduces a narrow onboarding-only service mapping because the operational mapping requires prices and availability.
- Allows 1–20 unique active service codes.
- Persists `SELF` or `ASSISTED` setup preference and explicit confirmation.
- Profile, draft selection mapping, setup step, and progress update run transactionally.

### API

- `GET /api/partner/setup/home-service`
- `PUT /api/partner/setup/home-service`

### Schema package

- `database/migrations/phase_4e/000_preflight_home_service_setup.sql`
- `database/migrations/phase_4e/001_home_service_setup.sql`
- `database/verification/verify_phase_4e_home_service_setup.sql`
- Marker: `2026_09_01_4e_home_service_setup`

### Deferred boundaries

No operational provider-service price rows, staff, schedules, slots, bookings, jobs, approval, verification, operational availability, or marketplace visibility are created or changed.

## 9. Phase 4F — Delivery Partner Setup

### Delivered behavior

- Uses `delivery_partners.id` exclusively; it never resolves Delivery onboarding through merchant `partners.id`.
- Adds a canonical seven-code vehicle master aligned with the inspected Delivery Admin vocabulary.
- Supports `OWNED`, `FAMILY`, `RENTED`, and `COMPANY_PROVIDED` ownership.
- Bicycle requires no registration, licence, or RC.
- Registered vehicle types require normalized registration, Driving Licence front, and Vehicle RC front.
- Vehicle insurance is optional for Phase 4F completion.
- Requirements are calculated by server-owned vehicle metadata; the frontend does not guess them.
- Partial saves remain resumable.
- One active primary vehicle is updated safely rather than duplicated.
- Vehicle changes immediately recalculate requirements and progress.
- Delivery remains pending and offline at 100%.

### API

- `GET /api/partner/setup/delivery`
- `PUT /api/partner/setup/delivery`
- Phase 4A document routes are reused for Delivery vehicle documents.

### Schema package

- `database/migrations/phase_4f/000_preflight_delivery_setup.sql`
- `database/migrations/phase_4f/001_delivery_setup.sql`
- `database/verification/verify_phase_4f_delivery_setup.sql`
- Marker: `2026_09_01_4f_delivery_setup`

### Deferred boundaries

No multi-vehicle management, live GPS, service radius, route, job, assignment, earnings, online-state transition, wallet credit, settlement, payout, or approval is implemented.

## 10. Phase 4G — Review, Submit & Setup Completion

### Delivered behavior

- Provides one server-composed safe review for Business and Delivery applications.
- Validates actual required step states rather than trusting `completion_percentage=100` alone.
- Requires explicit confirmation and an Idempotency-Key.
- Locks the current application row during submission.
- Transactionally changes the canonical application to submitted, sets first `submitted_at`, synchronizes the owner's coarse onboarding status, and records lifecycle history.
- Repeat submission is a safe no-op and does not duplicate lifecycle history.
- Correction resubmission reuses the same application/version and preserves the original submission timestamp.
- Submitted, under-review, approved, and rejected applications are read-only.
- Correction-required applications allow only sections whose step state is `needs_correction`.
- Bootstrap cannot mutate applications in locked lifecycle states.

### API

- `GET /api/partner/setup/review`
- `POST /api/partner/setup/submit`

### Review composition

Business review contains Personal, Business, Location, Business Hours, Operations, Documents, Bank, and the applicable Retail/Restaurant/Home Service module section.

Delivery review contains Personal, Location, Documents, Bank, and Delivery Setup. It excludes Business, Business Hours, Operations, and Retail/Restaurant/Home Service sections.

### Schema package

- `database/migrations/phase_4g/000_preflight_review_submit.sql`
- `database/migrations/phase_4g/001_review_submit.sql`
- `database/verification/verify_phase_4g_review_submit.sql`
- Marker: `2026_09_01_4g_review_submit`

### Lifecycle separation

- 100% is not submission.
- Submission is not approval.
- Approval is not marketplace activation.
- Review & Submit is not a tenth Business step or seventh Delivery step.

## 11. Final frontend experience

Protected Phase 4 routes:

- `/setup/documents`
- `/setup/bank`
- `/setup/retail`
- `/setup/restaurant`
- `/setup/home-service`
- `/setup/delivery`
- `/setup/review`

The final Setup Center and Dashboard use the server setup summary. At 100%, the user receives `Review & Submit`. Submitted applications show `View Submission` and `Under Review`. Correction-required applications route to the review page and expose edit actions only for requested sections. Saving or cancelling a correction returns to Review.

The review payload remains in memory and is not stored in localStorage. Bank plaintext and document contents are not stored in browser persistence. Existing 401 refresh/retry behavior remains centralized in the established authenticated API client.

## 12. Security and integrity controls

1. Authenticated Partner context derives all ownership.
2. Business and Delivery tables remain separated.
3. Request allowlists reject protected/unknown fields.
4. All setup mutations use server validation.
5. Idempotency protects repeat PUT/POST operations.
6. Transactions protect multi-table persistence.
7. Unique current/active keys prevent duplicate canonical rows.
8. Documents use content validation, private storage, opaque references, and authenticated streaming.
9. Bank accounts use authenticated encryption and masked projections.
10. Review excludes internal IDs, ciphertext, paths, and internal review notes.
11. Submission uses row locking and immutable transition history.
12. Lifecycle guards prevent unauthorized post-submission editing.
13. Correction editing is restricted to requested sections.
14. Audit metadata is deliberately narrow and excludes sensitive values.
15. No Phase 4 operation credits money or activates marketplace/operational state.

## 13. Audit events

Phase 4 adds safe audit events for:

- document uploaded/replaced/correction resolved/documents completed;
- bank updated/completed/account changed;
- Retail updated/completed/catalogue mode changed;
- Restaurant updated/completed/menu mode changed;
- Home Service updated/completed/preference changed;
- Delivery updated/completed/vehicle changed;
- application submitted/resubmitted.

Audit metadata contains only safe type/state/count/preference information. Full bank accounts, ciphertext, file paths, registration values, and numeric owner IDs are not deliberately audited by the Phase 4 controllers.

## 14. Final validation results

Validation rerun on 2026-09-02:

- `php api/partner/tests/run.php`: **470 assertions passed**.
- PHP parsing is necessarily exercised by the passing suite; the Phase 4 PHP files also passed targeted `php -l` checks during implementation.
- `npm.cmd run lint`: completed with **no errors**.
- Remaining lint output: two pre-existing Fast Refresh warnings in `PartnerSessionContext.jsx` and `PartnerSelectionContext.jsx`.
- `npm.cmd run build`: **passed** with Vite 8.2.1 and 1,899 transformed modules.
- Current production assets:
  - `frontend/dist/index.html`
  - `frontend/dist/assets/index-BeCtLrnI.css`
  - `frontend/dist/assets/index-lUaA70nE.js`
  - `frontend/dist/default-partner.svg`
  - `frontend/dist/favicon.svg`
  - `frontend/dist/icons.svg`
- Non-blocking advisory: the main JavaScript bundle is above 500 kB. Route-level code splitting is a later optimization, not an onboarding correctness blocker.

## 15. Complete SQL deployment order

The current workspace does not have a live database connection, so this report does not claim that production markers are present merely because migration files exist. Production must be verified through the supplied SQL.

For a clean sequential deployment:

1. Select database `u676721746_essivery` explicitly in phpMyAdmin.
2. Export a recoverable full backup, including `schema_migrations`.
3. Run Phase 4A preflight; stop on incompatible duplicates/schema.
4. Run Phase 4A migration once, then Phase 4A verification.
5. Configure and verify private document storage before enabling uploads.
6. Run Phase 4B preflight; stop on incompatible active-bank data.
7. Run Phase 4B migration once, then Phase 4B verification.
8. Configure `PARTNER_BANK_ENCRYPTION_KEY` and confirm OpenSSL.
9. Run Phase 4C preflight, migration once, and verification.
10. Run Phase 4D preflight, review active cuisine master/ownership, migration once, and verification.
11. Run Phase 4E preflight, review active Home Service master, migration once, and verification.
12. Run Phase 4F preflight, review existing vehicle rows/codes, migration once, and verification.
13. Run Phase 4G preflight, confirm no 100%-but-incomplete application anomalies, migration once, and verification.
14. Confirm all seven migration markers listed in this report exist exactly once.
15. Upload the complete matching PHP set.
16. Deploy the complete current frontend `dist` atomically.
17. Clear CDN/browser caches only as required; do not leave old `index.html` pointing to removed hashes.
18. Run the full smoke-test matrix below.

Every `ROLLBACK.md` is recovery guidance, not a script to run automatically. Prefer reverting application files while retaining harmless additive schema unless a separately approved, backup-supported destructive rollback is required.

## 16. Complete PHP/runtime deployment set

Upload these current files together, preserving paths:

### Documents

- `api/partner/config/documents.php`
- `api/partner/Middleware/DocumentUploadRequestMiddleware.php`
- `api/partner/Repositories/PartnerDocumentRepository.php`
- `api/partner/Services/PartnerDocumentStorage.php`
- `api/partner/Services/PartnerDocumentsService.php`
- `api/partner/Controllers/PartnerDocumentsController.php`
- `api/partner/storage/private/.htaccess`

### Bank

- `api/partner/config/bank.php`
- `api/partner/Middleware/BankDetailsRequestMiddleware.php`
- `api/partner/Repositories/PartnerBankAccountRepository.php`
- `api/partner/Services/PartnerBankEncryption.php`
- `api/partner/Services/PartnerBankService.php`
- `api/partner/Controllers/PartnerBankController.php`

### Retail

- `api/partner/Middleware/RetailSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerRetailRepository.php`
- `api/partner/Services/PartnerRetailService.php`
- `api/partner/Controllers/PartnerRetailController.php`

### Restaurant

- `api/partner/Middleware/RestaurantSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerRestaurantRepository.php`
- `api/partner/Services/PartnerRestaurantService.php`
- `api/partner/Controllers/PartnerRestaurantController.php`

### Home Service

- `api/partner/Middleware/HomeServiceSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerHomeServiceRepository.php`
- `api/partner/Services/PartnerHomeServiceService.php`
- `api/partner/Controllers/PartnerHomeServiceController.php`

### Delivery

- `api/partner/Middleware/DeliverySetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerDeliveryRepository.php`
- `api/partner/Services/PartnerDeliveryService.php`
- `api/partner/Controllers/PartnerDeliveryController.php`

### Review and shared integration

- `api/partner/Middleware/SetupMutabilityMiddleware.php`
- `api/partner/Repositories/PartnerReviewRepository.php`
- `api/partner/Services/PartnerReviewService.php`
- `api/partner/Controllers/PartnerReviewController.php`
- `api/partner/Services/PartnerSetupService.php`
- `api/partner/config/setup.php`
- `api/partner/routes/protected.php`
- `api/partner/index.php`

Do not deploy local tests unless the hosting release process intentionally includes test sources.

## 17. Complete frontend deployment set

Deploy the complete current `frontend/dist` directory, not old per-phase build hashes:

- `frontend/dist/index.html`
- `frontend/dist/assets/index-BeCtLrnI.css`
- `frontend/dist/assets/index-lUaA70nE.js`
- `frontend/dist/default-partner.svg`
- `frontend/dist/favicon.svg`
- `frontend/dist/icons.svg`

The previous hashes recorded in individual phase reports are historical and have been superseded by the final Phase 4G build.

## 18. End-to-end smoke-test matrix

### Common Business flow

1. Log in and refresh the Dashboard; session remains authenticated.
2. Upload Aadhaar front/back and PAN; verify private preview and refresh restoration.
3. Replace one document; confirm only one active row and retained inactive history.
4. Save Bank details; verify GET returns only the mask and last four.
5. Complete the applicable Retail, Restaurant, or Home Service module.
6. Confirm nine required steps and 100%.
7. Open Review & Submit; verify safe section composition and no internal IDs/secrets.
8. Submit once; verify submitted state and timestamp.
9. Submit/retry with the same or a new request safely; verify no duplicate lifecycle transition.
10. Refresh Dashboard; verify Under Review and read-only setup.

### Delivery flow

1. Select Bicycle, save partial/final state, and confirm no registration/DL/RC requirement.
2. Switch to Motorcycle; verify registration normalization and DL/RC requirements.
3. Upload required vehicle documents and verify authenticated previews.
4. Complete six required steps and reach 100% while remaining pending/offline.
5. Review Delivery application; verify Business-only sections are absent.
6. Submit and refresh; verify Under Review without online/job/earnings activation.

### Correction and isolation

1. Mark one document/bank/setup section correction-required through the Admin-side process.
2. Verify only that requested section is editable.
3. Correct it, return to Review, and resubmit.
4. Verify the same application/version is reused and original `submitted_at` is preserved.
5. Test a mobile number owning both merchant and Delivery identities; verify each application/data set remains isolated.
6. Confirm marketplace, referral, wallet, payout, order, booking, and job counts/states are unchanged.

## 19. Known deployment gates and residual risks

- Production schema/data can differ from repository assumptions; every preflight is mandatory.
- Phase 4A requires writable, non-public private storage, working `fileinfo`, and appropriate PHP upload limits.
- Phase 4B requires a protected encryption key, OpenSSL, and operational backup/key-retention procedures. Losing the key makes stored account ciphertext unrecoverable.
- Phase 4D requires a reviewed active cuisine master and unique non-deleted Restaurant ownership.
- Phase 4E requires reviewed active Home Service master data.
- Phase 4F requires review of legacy vehicle rows/codes if present.
- Phase 4G intentionally does not expose internal review notes because no canonical Partner-visible correction-reason field was proven; generic safe correction messages are used.
- Production multipart behavior, Apache deny rules, filesystem permissions, CDN rollout, and live auth refresh must be smoke-tested on Hostinger.
- The JavaScript bundle-size advisory remains an optimization item.

## 20. Final scope and readiness statement

Phase 4 completes the Partner-side onboarding collection and submission foundation:

- common verification documents;
- encrypted payout destination details;
- Retail, Restaurant, Home Service, and Delivery-specific setup;
- consolidated review;
- safe submission and repeat submission;
- correction/resubmission compatibility;
- lifecycle read-only enforcement;
- Business/Delivery identity isolation.

The next activity is **full end-to-end Partner onboarding testing**, not a new feature phase.

**PARTNER ONBOARDING DEVELOPMENT FROM PHASE 4A THROUGH PHASE 4G IS READY FOR FULL END-TO-END TESTING.**
