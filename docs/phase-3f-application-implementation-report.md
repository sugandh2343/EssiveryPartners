# Essivery Partner Phase 3F — Operations & Fulfilment implementation report

Date: 2026-08-30

## Outcome and schema verification

Phase 3F application support is implemented against the operator-applied `partner_fulfilment_settings` schema. The local migration and verification contract confirms the required fields and the unique `(partner_id,module_code)` owner. No migration was recreated or executed, and `partner_marketplace_settings` was not altered.

## Backend

Created:

- `api/partner/Middleware/OperationsRequestMiddleware.php`
- `api/partner/Repositories/PartnerFulfilmentRepository.php`
- `api/partner/Services/PartnerOperationsService.php`
- `api/partner/Controllers/PartnerOperationsController.php`

Modified:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php`

Routes:

- `GET /api/partner/setup/operations`
- `PUT /api/partner/setup/operations`

Both routes use the existing JWT, identity, Partner rate-limit, and PartnerContext middleware chain. PUT additionally uses a module-aware request allowlist and the existing idempotency middleware.

Module scope comes exclusively from authenticated PartnerContext. Retail supports `essiveryDelivery`, `selfDelivery`, and `customerPickup`; at least one must be true. Restaurant uses the same persistence contract with Takeaway/Customer Pickup wording. Home Service accepts only `serviceAtCustomerLocation`, which must be true. Delivery returns controlled `PARTNER_SETUP_NOT_APPLICABLE` and writes no merchant setting.

GET returns false defaults without inserting or completing the step when no canonical row exists. PUT performs one module-scoped upsert, marks Operations complete, recalculates setup progress, and records audit within a single transaction. A new key updates the same `(partner_id,module_code)` row; the same key is replay-safe through existing idempotency infrastructure.

Successful Operations completion normally advances a business setup from 5/9 to 6/9 (server-derived 67%) and centrally resolves `documents` as the next step. No percentage or route is hardcoded in the page.

Audit events:

- `partner_setup.operations_updated`, with enabled mode names
- `partner_setup.operations_completed`

## Isolation boundaries

Operations writes only `partner_fulfilment_settings`. It does not modify marketplace visibility, operational status, serviceability status, approval, service areas, zones, pickup addresses, delivery assignments, minimum order, preparation time, radius, fee, authentication, referral, wallet, or Delivery Partner records.

## Frontend

Created:

- `frontend/src/pages/OperationsPage.jsx`

Modified:

- `frontend/src/services/partner/partnerSetupService.js`
- `frontend/src/App.jsx`

The protected `/setup/operations` page restores saved values through GET, presents only module-supported choices, supports Save & Return and Save & Continue, updates setup cache, follows backend next-step metadata, and protects unsaved changes during browser unload. Delivery direct navigation safely returns to Setup Center after the controlled not-applicable response.

## Test fixture and results

The SQLite fixture now contains the migrated `partner_fulfilment_settings` shape and its composite unique key. Coverage includes Retail/Restaurant/Home Service contracts, invalid combinations and protected fields, canonical updates, module isolation for one Partner, Delivery exclusion, safe GET restoration, setup progress/next step, and marketplace non-interference.

- PHP syntax: all Partner PHP files passed.
- Partner suite: **228 assertions passed**.
- Frontend lint: passed with only two pre-existing Fast Refresh warnings in Partner context files.
- Frontend production build: passed.

Remote production runtime testing was not performed from this workspace.

## Deployment

Upload these PHP files while preserving paths:

1. `api/partner/Middleware/OperationsRequestMiddleware.php`
2. `api/partner/Repositories/PartnerFulfilmentRepository.php`
3. `api/partner/Services/PartnerOperationsService.php`
4. `api/partner/Controllers/PartnerOperationsController.php`
5. `api/partner/Services/PartnerSetupService.php`
6. `api/partner/routes/protected.php`

Upload the complete contents of `frontend/dist/` to the Partner frontend root. Replace `index.html` and publish its referenced hashed assets together. Remove obsolete hashed assets only after the new entry file is live.

Run **no new SQL**. The operator has already executed migration `2026_08_30_3f_partner_fulfilment`. The existing Phase 3F verification SQL may be rerun read-only if deployment evidence is needed.

## Smoke test

1. Grocery: enable Essivery Delivery, save, refresh, and confirm it restores.
2. Confirm Setup Center/Dashboard show server-derived 67% and Documents next.
3. Grocery: switch to Self Delivery plus Pickup and confirm only one RETAIL row remains.
4. Restaurant: enable Essivery Delivery plus Takeaway and confirm a RESTAURANT row.
5. Home Service: confirm only Service at Customer Location appears and saves.
6. Submit no Retail modes and confirm a friendly validation response without changing the saved row.
7. Delivery: confirm no Operations card and direct navigation creates no fulfilment row.
8. Confirm marketplace visibility, runtime status, approval, and serviceability remain unchanged.

## Remaining risk and readiness

The remaining risk is deployment/runtime verification: production must contain the already-approved columns/index, and shared audit/idempotency infrastructure must be healthy. Subject to upload and smoke testing, Phase 3F is application-complete and ready for Phase 3G Documents.
