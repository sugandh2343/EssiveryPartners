# Phase 4B — Bank & Payout Details Application Implementation

## Status

Phase 4B application implementation is **complete locally** against the approved Phase 4B target schema.

> **PRODUCTION DEPLOYMENT BLOCKED UNTIL PHASE 4B SQL + ENV CONFIGURATION ARE COMPLETE.**

No production migration was executed by this local implementation.

## Canonical ownership and storage

- Business: `partners.id -> partner_bank_accounts.partner_id`
- Delivery: `delivery_partners.id -> delivery_partner_bank_accounts.delivery_partner_id`
- Table selection is derived exclusively from `PartnerContext`.
- No wallet, ledger, settlement, payout, statement, marketplace, visibility, serviceability, or online-state table is read or changed.
- The approved current-plus-inactive-history model is used. Exactly one active record is permitted per owner by the generated active-owner uniqueness key.

## Encryption and configuration

- AES-256-GCM through PHP OpenSSL.
- Environment secret: `PARTNER_BANK_ENCRYPTION_KEY`; no default and no committed secret.
- Secret must be at least 32 characters; SHA-256 derives the 256-bit binary key.
- Every encryption uses a random 12-byte IV and 16-byte authentication tag.
- Versioned binary envelope: one version byte, IV, tag, ciphertext.
- Current version is centralized as `PartnerBankEncryption::CURRENT_BANK_ENCRYPTION_VERSION` (`1`).
- Missing/short key, unavailable OpenSSL, unsupported envelope version, and failed authentication all fail closed with controlled errors.
- GET masks from `account_number_last4`; it never decrypts merely to render a mask.

## API

- `GET /api/partner/setup/bank`
- `PUT /api/partner/setup/bank`

Both routes use JWT, Partner identity/context, rate limiting, and initialized setup conventions. PUT additionally requires `Idempotency-Key`, validates a strict field allowlist, and uses the existing idempotency middleware.

PUT contract:

```json
{
  "accountHolderName": "Riya Singh",
  "accountNumber": "123456789012",
  "confirmAccountNumber": "123456789012",
  "ifsc": "HDFC0001234"
}
```

GET/PUT safe bank projection contains only `hasBankDetails`, `accountHolderName`, `maskedAccountNumber`, `last4`, `ifsc`, normalized `reviewStatus`, and `updatedAt`, plus safe setup/step summaries. It never exposes full account numbers, ciphertext, envelope internals, numeric owner/row IDs, or encryption version.

## Validation and behavior

- Account-holder whitespace is normalized; supported characters are Unicode letters, spaces, periods, apostrophes, and hyphens; length 2–150.
- Account number is digits only, 6–20 characters.
- Confirmation must exactly match after surrounding whitespace normalization and is never persisted.
- IFSC is uppercased and structurally checked using `^[A-Z]{4}0[A-Z0-9]{6}$`.
- Material change means holder name, account number, or IFSC changed.
- A changed or correction-required record is retired and replaced by a new active `pending` record; history remains inactive.
- Identical normal resaves do not create unnecessary history.
- `approved`, `pending`, `rejected`, and DB correction variants normalize to the API vocabulary `approved`, `pending`, `rejected`, `needs_correction`.
- Rejected/correction-required current data regresses the Bank setup step. Resubmission creates a new pending record and restores data-entry completion.
- Save is transactional across replacement, setup/progress synchronization, and audit callback.

## Audit and plaintext handling

Events implemented:

- `partner_setup.bank_updated`
- `partner_setup.bank_completed`
- `partner_setup.bank_account_changed` for material changes

Audit metadata contains only `last4`, `ifsc`, and `reviewState`. Controller code never receives or audits ciphertext/envelope material. No local Partner request-body logger exists in this repository; the strict middleware allowlist and audit projection avoid adding a new raw-body logging path. Deployment should retain upstream redaction for `accountNumber` and `confirmAccountNumber` if Hostinger has infrastructure-level body logging.

Plain account inputs exist only in the request/service scope and frontend component state. The frontend clears both raw number fields after success and never places them in localStorage, sessionStorage, setup cache, URL, logs, or API response state.

## Setup progress and routing

Bank entry completion is independent of Admin approval. `PartnerSetupService` recalculates progress; percentages are not hardcoded.

- Business after Bank: 8/9 required steps, 89% with preceding steps complete.
- Delivery after Bank: 5/6 required steps, 83% with preceding steps complete.
- Next step remains server-derived: Retail `retailSetup`, Restaurant `restaurantSetup`, Home Service `homeServiceSetup`, Delivery `deliverySetup` (existing API camel-case convention; each carries its canonical route code).

## Frontend

Route: `/setup/bank`

The page provides add, safe saved-summary, and replace modes; password-style account inputs with show/hide; full re-entry on edit; masked saved display; review/correction status; unsaved navigation/browser-refresh warning; safe success summary; and server-derived continuation routing.

## Files created

### PHP/backend

- `api/partner/config/bank.php`
- `api/partner/Middleware/BankDetailsRequestMiddleware.php`
- `api/partner/Repositories/PartnerBankAccountRepository.php`
- `api/partner/Services/PartnerBankEncryption.php`
- `api/partner/Services/PartnerBankService.php`
- `api/partner/Controllers/PartnerBankController.php`

### Frontend

- `frontend/src/pages/BankDetailsPage.jsx`

## Files modified

- `api/partner/index.php`
- `api/partner/routes/protected.php`
- `api/partner/Services/PartnerSetupService.php`
- `api/partner/tests/run.php`
- `frontend/src/App.jsx`
- `frontend/src/services/partner/partnerSetupService.js`

## Verification results

- Full Partner suite: **298 assertions passed** (previous Phase 4A coverage remains included).
- PHP syntax: all touched Partner PHP files passed.
- Frontend lint: passed with two existing `react(only-export-components)` Fast Refresh warnings in Partner context files.
- Frontend production build: passed. Existing advisory: main JS chunk exceeds 500 kB.
- Built artifacts:
  - `frontend/dist/index.html`
  - `frontend/dist/assets/index-RwPPvts2.css`
  - `frontend/dist/assets/index-COrKyfXg.js`
  - `frontend/dist/default-partner.svg`
  - `frontend/dist/favicon.svg`
  - `frontend/dist/icons.svg`

Coverage includes empty/safe GET, Business types, Delivery, validation, normalization, no plaintext response/storage, AES-GCM roundtrip/random IV/corruption/short-key failure, replacement history, correction regression/resubmission, ownership isolation, progress, next routing, route safety, and audit-source redaction. Existing authentication/policy tests continue to reject Customer and unsupported identities. Existing idempotency middleware provides same-key replay without duplicate controller execution.

## Production deployment gate and order

When Hostinger is available:

1. Run `database/migrations/phase_4b/000_preflight_bank_accounts.sql`.
2. Review every result and stop on incompatible existing structures.
3. Back up the database.
4. Run once: `database/migrations/phase_4b/001_secure_bank_accounts.sql`.
5. Run `database/verification/verify_phase_4b_bank_accounts.sql` and confirm PASS/no duplicate-active rows.
6. Configure `PARTNER_BANK_ENCRYPTION_KEY` with a strong random secret of at least 32 characters. Do not send or commit it.
7. Confirm PHP OpenSSL is enabled (`extension_loaded('openssl')`).
8. Upload the created/modified PHP files listed above (excluding tests for production if tests are not deployed).
9. Deploy the six current `frontend/dist` files listed above, removing obsolete hashed assets only through the normal safe deployment process.
10. Smoke-test one Business save/GET and one Delivery save/GET, confirming only last four digits return and one active row exists per owner.

An operator can generate a secret outside the repository, for example with `openssl rand -base64 48`, and place it directly in the protected hosting environment configuration.

## Remaining risks/readiness

- Production remains blocked until SQL, verification, environment key, and OpenSSL checks pass.
- Production backup/restore procedures and upstream infrastructure log redaction must be confirmed operationally.
- No external Bank/IFSC ownership verification or payout activation is included, by design.
- Code is ready to deploy immediately **after** the production gate passes.
- From the local development perspective, Phase 4B is ready and Phase 4C Retail Setup can begin.
