# Phase 4D — Restaurant Setup complete report

## Status

Phase 4D schema and application implementation is complete locally.

> **PRODUCTION DEPLOYMENT BLOCKED UNTIL PHASE 4D SQL IS EXECUTED AND VERIFIED.**

No production SQL was executed.

## Architecture and schema findings

- Canonical authenticated Business owner: `partners.id`; the existing operational Restaurant projection is linked through `restaurants.owner_user_id -> users.id`, resolved only through PartnerContext and the owned Partner's `user_id`.
- Canonical module membership: `partner_business_modules` with server-derived `RESTAURANT`.
- Canonical Restaurant profile: `restaurants`, deterministically owned by `owner_user_id` in the live schema.
- Existing canonical cuisine master: `cuisines`, exposed by safe slug/name only.
- Normalized selection mapping: `restaurant_cuisines` (`restaurant_id + cuisine_id`).

The repository had no authoritative application contract for Restaurant type or VEG/NON_VEG/BOTH discovery. Those fields were not invented. The minimum implemented contract is cuisines, menu setup preference, and explicit confirmation.

The initial target assumption was reconciled after production inspection: the live table uses `owner_user_id`, not `partner_id`, and the live `restaurant_cuisines` table already uses composite `(restaurant_id,cuisine_id)` ownership. The resumable migration now preserves those contracts, adds only `menu_setup_mode`, `setup_confirmed_at`, and unique active identity ownership. Production preflight must stop if duplicate non-deleted `owner_user_id` rows exist.

No cuisine seed is included. Production must have reviewed active rows in its existing `cuisines` master.

## Migration package

- `database/migrations/phase_4d/000_preflight_restaurant_setup.sql`
- `database/migrations/phase_4d/001_restaurant_setup.sql`
- `database/verification/verify_phase_4d_restaurant_setup.sql`
- `database/migrations/phase_4d/ROLLBACK.md`
- `docs/phase-4d-schema-reconciliation-report.md`

The preflight is safe when `restaurants` or `restaurant_cuisines` is absent: it emits an inspection note rather than querying a missing table.

## Decisions and deferred fields

- Restaurant type: deferred; no authoritative canonical option model/consumer was found.
- Food preference: deferred; no authoritative VEG/NON_VEG/BOTH consumer contract was found.
- Cuisines: implemented through active DB master options, 1–5 required, unique lowercase codes, unknown/inactive codes rejected.
- Menu preference: required `SELF` or `ASSISTED`; preference only.
- FSSAI/GST: not introduced; category-specific verification belongs to a later explicit verification design.
- Preparation time/minimum order/packaging: deferred.
- Restaurant media: deferred; private identity-document storage is not reused.
- Menu items, dishes, categories, prices, addons, variants, inventory, orders, kitchen state, table booking, approval, and marketplace activation: not implemented.

## Backend

Created:

- `api/partner/Middleware/RestaurantSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerRestaurantRepository.php`
- `api/partner/Services/PartnerRestaurantService.php`
- `api/partner/Controllers/PartnerRestaurantController.php`

Modified:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php`

Routes:

- `GET /api/partner/setup/restaurant`
- `PUT /api/partner/setup/restaurant`

Both routes use JWT, Partner identity/context, rate limiting, and initialized setup conventions. PUT requires `Idempotency-Key` and uses the central idempotency middleware.

PUT accepts exactly:

```json
{"cuisines":["north-indian","chinese"],"menuSetupMode":"SELF","confirmed":true}
```

All owner/module/identity/approval/marketplace/status/timestamp fields and every unknown field are rejected by the strict allowlist. Cuisine SQL uses generated placeholders and bound values, never raw string interpolation.

The transaction validates all cuisines before mutation, resolves the Restaurant through PartnerContext, creates a missing profile from existing Business Name and completed canonical city, keeps it `inactive`/`closed`, ensures the module, deactivates prior mappings, upserts selected natural mappings, stores preference/confirmation, completes `restaurant_setup`, recalculates progress, audits, and commits. Failure rolls back fully. Existing idempotency middleware prevents repeated same-key controller side effects; a new key updates the same canonical profile/mappings.

Safe audit events:

- `partner_setup.restaurant_updated`
- `partner_setup.restaurant_completed`
- `partner_setup.restaurant_menu_mode_changed`

Metadata contains only cuisine codes/count and menu mode—no numeric IDs.

## API contracts

GET/PUT safely return Business Name, constant Restaurant identity, selected cuisine code/name values, menu mode, confirmation, active cuisine options, maximum selection count, and safe setup/step summaries. No numeric Restaurant, Partner, User, Cuisine, mapping, application, or step IDs are returned.

Restaurant only is allowed. Retail, Home Service, and Delivery receive controlled `PARTNER_SETUP_NOT_APPLICABLE`; Customer remains rejected by the established identity policy. Same-mobile Grocery and Restaurant users remain isolated by PartnerContext/Partner ownership.

With all previous steps complete, central progress becomes 9/9 = 100%. This does not submit, approve, publish, enable orders, activate payouts, or make the Restaurant live. `nextRecommendedStep` becomes null, and the UI returns safely to Setup Center. A later Documents correction regresses overall readiness through central calculation.

## Frontend

Created:

- `frontend/src/pages/RestaurantSetupPage.jsx`

Modified:

- `frontend/src/App.jsx`
- `frontend/src/services/partner/partnerSetupService.js`

Route: `/setup/restaurant`.

The responsive page includes a read-only Restaurant summary, searchable server-provided cuisine chips, 1–5 selected counter, large menu-preference cards, required confirmation, menu boundary information, Save & Return, Complete Restaurant Setup, API restoration after refresh, and unsaved-change protection. It contains no menu editor and stores no local-only source of truth.

Dashboard behavior from Phase 4C already shows “Business setup information is complete” and “Review Setup” at 100%, explicitly stating that the business is not approved/live.

## Side-effect verification

Restaurant save does not create or modify menu/product rows, prices, stock, inventory, order acceptance, kitchen/runtime state, Delivery data, marketplace settings, approval, wallet, payout, or settlement state.

## Quality results

- Full Partner suite: **373 assertions passed**, including all Phase 4C and Phase 4B regressions.
- All touched PHP files: syntax valid.
- Frontend lint: passed with the same two existing `react(only-export-components)` Fast Refresh warnings.
- Production build: passed.
- Existing advisory: main JS chunk exceeds 500 kB.

## Exact production deployment sequence

1. Run `database/migrations/phase_4d/000_preflight_restaurant_setup.sql`.
2. Review the active cuisine master, existing Restaurant schemas, ownership, indexes, and duplicate checks. Stop if `cuisines` is absent/incompatible or duplicate non-deleted `restaurants.owner_user_id` rows exist.
3. Back up the database and verify restore availability.
4. Run once: `database/migrations/phase_4d/001_restaurant_setup.sql`.
5. Run `database/verification/verify_phase_4d_restaurant_setup.sql`; require all PASS/no duplicate results.
6. Upload exactly:
   - `api/partner/Middleware/RestaurantSetupRequestMiddleware.php`
   - `api/partner/Repositories/PartnerRestaurantRepository.php`
   - `api/partner/Services/PartnerRestaurantService.php`
   - `api/partner/Controllers/PartnerRestaurantController.php`
   - `api/partner/Services/PartnerSetupService.php`
   - `api/partner/routes/protected.php`
7. Deploy exactly:
   - `frontend/dist/index.html`
   - `frontend/dist/assets/index-B_17nwW6.css`
   - `frontend/dist/assets/index-C3Xi3ucc.js`
   - `frontend/dist/default-partner.svg`
   - `frontend/dist/favicon.svg`
   - `frontend/dist/icons.svg`
8. Smoke-test Restaurant GET empty state, SELF save, refresh restoration, ASSISTED update, 100% dashboard language, Grocery NOT_APPLICABLE, and unchanged marketplace/menu/order counts.

No new environment variable is required. No master-data seed should be run unless preflight proves the existing cuisine master is unsuitable; that would require separate reviewed business data rather than guessed values.

## Risks and readiness

The repository does not contain the authoritative historic CREATE TABLE for production `restaurants`/`cuisines`; therefore preflight review is a mandatory deployment gate. If existing Restaurant ownership or cuisine columns differ, stop rather than adapting blindly.

Application rollback is preferred. Do not drop a pre-existing `restaurants` table. Destructive mapping/column rollback requires backup and explicit approval.

From the local development perspective, Phase 4D is complete and ready for **Phase 4E Home Service Setup**.
