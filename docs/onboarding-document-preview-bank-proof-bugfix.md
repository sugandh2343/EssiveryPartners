# Essivery Partner onboarding production bugfix

Date: 2026-09-04

Status: application implementation complete and locally verified; production deployment and authenticated Hostinger smoke testing remain.

## Resolved behavior

- Aadhaar and PAN images are loaded through the authenticated private-document endpoint and rendered as Blob previews.
- PDFs open from authenticated Blob URLs.
- A saved row whose private file cannot be read is now shown as **Preview unavailable** with a useful replacement message, rather than incorrectly saying **No document uploaded**.
- Bank setup accepts a required cancelled-cheque/passbook proof as JPEG, PNG, WebP, or PDF, up to 5 MB.
- Bank proof uses document code `BANK_PROOF` and the existing private document storage pipeline.
- Business proof rows are stored in `partner_documents`; Delivery proof rows are stored in `delivery_partner_documents`.
- Replacement retires the previous active proof and preserves history. The active-document uniqueness rule continues to allow exactly one current proof per owner.
- The Bank setup step completes only when both a valid encrypted Bank account row and a current proof are present and neither needs correction.
- Bank proof appears in the final Review projection.
- Bank-proof upload has its own `/setup/bank/proof` route and is authorized as part of the Bank step, including correction-mode rules.
- The earlier bootstrap-body problem remains fixed twice: only bodyless bootstrap endpoints register empty-body validation, and the middleware itself ignores every non-bootstrap path. Normal Bank PUT requests therefore accept their validated JSON body even if an older/cached route list accidentally attaches the validator.

## Database impact

No SQL migration is required for this bugfix. Existing Phase 4A tables already support arbitrary document types and already contain the required private reference, review status, history status, generated active-document key, and owner-scoped unique index.

Bank numbers continue to be encrypted in `partner_bank_accounts` or `delivery_partner_bank_accounts`; no full account number is returned by the API. The proof file itself is stored in private filesystem storage, while its owner-scoped metadata/reference is stored in the corresponding document table.

## Production files to upload together

Backend/runtime:

1. `api/partner/Controllers/PartnerDocumentsController.php`
2. `api/partner/Controllers/PartnerBankController.php`
3. `api/partner/Services/PartnerDocumentsService.php`
4. `api/partner/Services/PartnerBankService.php`
5. `api/partner/Services/PartnerReviewService.php`
6. `api/partner/Middleware/SetupMutabilityMiddleware.php`
7. `api/partner/Middleware/EmptyBodyRequestMiddleware.php`
8. `api/partner/Middleware/RequireIdempotencyKeyMiddleware.php`
9. `api/partner/routes/protected.php`

Deploy the complete current `frontend/dist` atomically:

- `frontend/dist/index.html`
- `frontend/dist/assets/index-B9FZFOYF.css`
- `frontend/dist/assets/index-BoGTFEzM.js`
- `frontend/dist/default-partner.svg`
- `frontend/dist/favicon.svg`
- `frontend/dist/icons.svg`

Do not combine the new `index.html` with an older hashed JavaScript bundle. The screenshot message **The bootstrap request body must be empty** proves that the running API/frontend set was not yet the complete matching local release.

## Required production configuration

- Keep `PARTNER_BANK_ENCRYPTION_KEY` configured with the existing production key. Changing it makes existing encrypted account numbers unreadable.
- The Partner entry point loads the shared User API environment (normally `public_html/api/user/.env`), not a frontend `.env`. Add the key there as `PARTNER_BANK_ENCRYPTION_KEY=<stable random value of at least 32 characters>` and do not place it in the GitHub repository.
- Keep `ESSIVERY_PARTNER_DOCUMENT_STORAGE` pointed at a persistent, writable, non-public directory, or use the protected fallback already supplied by Phase 4A.
- PHP `fileinfo` must be enabled.
- `upload_max_filesize` and `post_max_size` must allow a 5 MB document plus multipart overhead.
- The PHP process must have create/read/write permission on private storage.

## Verification completed locally

- Partner backend suite: 503 assertions passed.
- Focused onboarding frontend suite: 14 assertions passed.
- PHP syntax validation passed for every changed PHP route, controller, middleware, and service.
- Frontend lint passed with only the two existing Fast Refresh warnings.
- Production frontend build passed.
- Tested Bank proof absence, completion gating, business/delivery ownership isolation, replacement history, image/PDF type projection, encrypted Bank persistence, and setup next-step progression.

## Production smoke test

1. Sign in and refresh once to confirm the session remains valid.
2. Open Documents and verify each saved Aadhaar/PAN image appears; open any saved PDF.
3. Replace one image, refresh, and confirm the replacement preview remains.
4. Open Bank Details and save valid Bank fields. Confirm no bootstrap-body error appears.
5. Upload a cancelled cheque or passbook. Confirm its preview appears and remains after refresh.
6. Confirm the Bank step does not complete with Bank fields alone, then completes once proof is uploaded.
7. Replace the proof and verify the new preview.
8. Open Review and confirm masked Bank details plus Bank-proof status are present.
9. Complete the identity-specific setup and submit onboarding.
