# Phase 4C — Retail Store Setup complete report

## 1. Local status and production gate

Phase 4C schema and application implementation is complete locally.

> **PRODUCTION DEPLOYMENT BLOCKED UNTIL PHASE 4C SQL IS EXECUTED AND VERIFIED.**

No production SQL was executed. Phase 4A/4B production prerequisites remain independent prerequisites if they are still pending.

## 2. Schema inspection and architecture

Inspected Partner setup/context code, migrations, diagnostics, Admin/User-facing category readers, `partners`, `users`, `parent_categories`, `partner_business_modules`, `partner_retail_parent_categories`, `partner_marketplace_settings`, onboarding tables, catalogue/product references, inventory references, and audit integration.

- Canonical Business owner: `partners.id`.
- Canonical Retail module membership: `partner_business_modules` keyed by Partner and `RETAIL`.
- Canonical Retail-category association: `partner_retail_parent_categories` keyed by `partner_id + parent_category_id`.
- Category is derived from `PartnerContext.parentCategoryId` and the authenticated identity. The request cannot select it.
- Existing `partner_retail_parent_categories` had no proven fields for explicit confirmation or catalogue-onboarding preference.

A migration is required. The canonical association is minimally extended with:

- `catalogue_setup_mode VARCHAR(20) NULL`
- `retail_confirmed_at DATETIME(6) NULL`
- unique `(partner_id,parent_category_id)` protection

No `retail_partners` or `partner_retail_setup` table was created. The application reuses an existing mapping or creates only the deterministic authenticated mapping when missing. Legacy mapping presence alone does not complete setup.

## 3. Migration package

- `database/migrations/phase_4c/000_preflight_retail_setup.sql`
- `database/migrations/phase_4c/001_retail_setup.sql`
- `database/verification/verify_phase_4c_retail_setup.sql`
- `database/migrations/phase_4c/ROLLBACK.md`
- `docs/phase-4c-schema-reconciliation-report.md`

The migration is additive and does not insert mappings, products, inventory, catalogue data, or marketplace state.

## 4. Product decisions

Catalogue preference is persisted with required API values `SELF` or `ASSISTED`. It is only a future onboarding/support preference and creates no support ticket, notification, product, or catalogue workflow.

The category projection contains safe `code`, `slug`, `name`, and optional `iconUrl`, plus the existing Business Name. It contains no numeric IDs.

Storefront media is deferred. `partner_marketplace_settings.logo_url` is Partner-wide and no proven Retail-specific media lifecycle exists. Store type, minimum order, preparation time, delivery radius/fees, runtime state, products, price, stock, inventory, and catalogue publication are also deferred.

## 5. Backend implementation

Created:

- `api/partner/Middleware/RetailSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerRetailRepository.php`
- `api/partner/Services/PartnerRetailService.php`
- `api/partner/Controllers/PartnerRetailController.php`

Modified:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/config/setup.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php`

Routes:

- `GET /api/partner/setup/retail`
- `PUT /api/partner/setup/retail`

Both use the established JWT, identity, rate-limit, and PartnerContext chain. PUT also uses strict request validation, required `Idempotency-Key`, and the central idempotency middleware.

PUT accepts exactly:

```json
{"catalogueSetupMode":"SELF","confirmed":true}
```

`confirmed` must be literal `true`; mode is normalized uppercase and restricted to `SELF`/`ASSISTED`. All other fields—including owner, category, module, approval, marketplace, status, and timestamp fields—are rejected by the allowlist with 422.

PUT transactionally ensures the deterministic `RETAIL` module/category associations, saves the preference and confirmation timestamp, completes `retail_setup`, recalculates progress, records audit events, and commits. Failure rolls back. Same-key replay is handled before controller execution by existing idempotency infrastructure; a new key safely updates the same unique association.

Safe audit events:

- `partner_setup.retail_updated`
- `partner_setup.retail_completed`
- `partner_setup.retail_catalogue_mode_changed` when applicable

Metadata contains only category code and catalogue mode, never numeric owner/category IDs.

## 6. Setup state and isolation

Completion requires a valid server-derived Retail category, persisted mode, and explicit confirmation. With all preceding steps complete, central calculation returns 9/9 and 100%. It does not change Partner approval, application submission, marketplace visibility, catalogue readiness, or live state. `nextRecommendedStep` safely becomes `null`; no nonexistent Review route is opened.

An earlier required-step correction still regresses progress (tested with Documents: 100% to 89%). Retail completion does not pin overall readiness.

Allowed identities: Grocery, Vegetable, Pharmacy, Fashion, Electronics. Restaurant, Home Service, and Delivery receive controlled `PARTNER_SETUP_NOT_APPLICABLE`; Customer remains rejected by the existing identity policy. Separate users/Partners sharing a mobile remain isolated through PartnerContext ownership.

## 7. Safe API projection

GET/PUT return:

```json
{
  "retail": {
    "category": {"code":"grocery","slug":"grocery","name":"Grocery","iconUrl":null},
    "businessName":"Fresh Basket",
    "catalogueSetupMode":"SELF",
    "confirmed":true
  },
  "step": {},
  "setup": {}
}
```

Numeric Partner, User, parent-category, mapping, application, and step IDs are never projected.

## 8. Frontend implementation

Created:

- `frontend/src/pages/RetailSetupPage.jsx`

Modified:

- `frontend/src/App.jsx`
- `frontend/src/services/partner/partnerSetupService.js`
- `frontend/src/pages/DashboardPage.jsx`

Route: `/setup/retail`.

The responsive page shows a read-only category/Business card, Contact Support link, two large catalogue-mode cards, required confirmation, product-boundary information, Save & Return, Complete Retail Setup, refresh restoration from the API, and established unsaved-change protection. It stores no Retail setup values in localStorage. At 100%, Dashboard says the information is ready for review and explicitly avoids approved/live language; its CTA returns safely to Setup Center because submission is a later phase.

## 9. Side-effect boundaries

Tests and repository inspection confirm Retail save does not create or modify master products, Partner-product assignments, catalogue publication, inventory, stock, prices, marketplace settings, approval, wallets, settlements, Delivery data, Restaurant data, or Home Service data.

## 10. Verification

- Full Partner suite: **344 assertions passed**.
- All touched PHP files: syntax valid.
- Frontend lint: passed with the same two pre-existing `react(only-export-components)` Fast Refresh warnings in Partner context files.
- Frontend production build: passed.
- Existing advisory: main JS chunk exceeds 500 kB.

Coverage includes all five Retail categories, read-only category projection, valid saves, explicit confirmation/mode validation, protected-field source allowlist, existing/missing mapping behavior, repeat updates, same-mobile identity isolation, non-Retail exclusions, safe projections, no catalogue/inventory/marketplace side effects, central 100% progress, correction regression, refresh restoration, audit safety, and all Phase 4B regressions.

## 11. Exact production sequence

1. Run `database/migrations/phase_4c/000_preflight_retail_setup.sql`.
2. Confirm the correct database, all prerequisite tables, compatible canonical columns/indexes, and zero duplicate `(partner_id,parent_category_id)` rows. Stop on any mismatch.
3. Back up the production database and verify restore availability.
4. Run once: `database/migrations/phase_4c/001_retail_setup.sql`.
5. Run `database/verification/verify_phase_4c_retail_setup.sql`; require all PASS results and no duplicate rows.
6. Upload these PHP files:
   - `api/partner/Middleware/RetailSetupRequestMiddleware.php`
   - `api/partner/Repositories/PartnerRetailRepository.php`
   - `api/partner/Services/PartnerRetailService.php`
   - `api/partner/Controllers/PartnerRetailController.php`
   - `api/partner/Services/PartnerSetupService.php`
   - `api/partner/config/setup.php`
   - `api/partner/routes/protected.php`
7. Deploy the exact current frontend build:
   - `frontend/dist/index.html`
   - `frontend/dist/assets/index-CmHLbDbv.css`
   - `frontend/dist/assets/index-DenNPA4F.js`
   - `frontend/dist/default-partner.svg`
   - `frontend/dist/favicon.svg`
   - `frontend/dist/icons.svg`
8. Smoke-test GET/PUT for one Retail identity, refresh restoration, 100% Setup Center/Dashboard wording, a Restaurant NOT_APPLICABLE response, and unchanged marketplace/product counts.

No new environment variable is required by Phase 4C. Phase 4B still requires its encryption environment key before deploying the Bank implementation.

## 12. Risks and next-phase readiness

Production compatibility is gated by reviewing the preflight output because the repository does not contain a full authoritative CREATE TABLE history for the existing Retail association. Deployment must stop if production lacks the expected `public_id`, owner/category, status, or timestamp contract.

Rollback should prefer reverting application files while retaining the harmless nullable columns. Destructive column/index rollback requires explicit approval and confirmation that Phase 4C values are no longer required.

From the local development perspective, Phase 4C is complete and the architecture is ready for **Phase 4D Restaurant Setup**.
