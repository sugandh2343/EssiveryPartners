# Phase 4E — Home Service Setup complete report

**Date:** 2026-09-01  
**Status:** Phase 4E schema and application implementation is complete locally.

> **PRODUCTION DEPLOYMENT BLOCKED UNTIL THE PHASE 4E PREFLIGHT, MIGRATION, AND VERIFICATION ARE COMPLETED.**

## 1–8. Schema and ownership findings

The authoritative Phase 21A migration was inspected directly at `C:\Users\pc\Documents\Essivery\api\database\migrations\phase_21\01_home_services_foundation.sql`.

- Canonical owner: `partners.id -> home_service_provider_profiles.partner_id` (one profile per Partner).
- Canonical service master: `home_service_masters`, grouped by `home_service_categories` and optional `home_service_subcategories`.
- Existing operational mapping: `home_service_provider_services(provider_id,service_id)`. It requires `provider_price` and represents later provider-service availability/pricing. Phase 4E deliberately does not write it.
- Phase 4E setup mapping: `home_service_provider_setup_services(provider_id,service_id)`. It records draft onboarding selections only and has no pricing, staff, schedule, availability, approval, or marketplace fields.
- Module association remains `partner_business_modules(partner_id,'HOME_SERVICE')`.
- Migration is required because the existing profile has no setup preference/confirmation fields and the operational mapping cannot represent price-free onboarding selections safely.
- The migration adds `setup_preference` and `setup_confirmed_at` to the canonical provider profile and creates only the narrow setup-selection mapping.
- The service hierarchy is retained. The API returns active category/subcategory groups and selection occurs at the active service-master leaf.

## 9–12. Product rules

- Selection rule: minimum 1, maximum 20 unique active service codes.
- Setup preference: exactly `SELF` or `ASSISTED`.
- Explicit `confirmed=true` is required.
- Experience years is left unchanged in the existing provider profile. Media, licence details, pricing, staff, schedules, bookings, jobs, approval, application submission, and marketplace activation are deferred.

## 13–17. Application surface

New backend files:

- `api/partner/Controllers/PartnerHomeServiceController.php`
- `api/partner/Middleware/HomeServiceSetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerHomeServiceRepository.php`
- `api/partner/Services/PartnerHomeServiceService.php`

Modified backend files:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php` (local automated coverage)

Routes:

- `GET /api/partner/setup/home-service`
- `PUT /api/partner/setup/home-service`

GET returns the safe provider summary, selected service codes/names, grouped active master options, preference, confirmation state, step, and central setup summary. PUT accepts only:

```json
{
  "services": ["deep-home-cleaning"],
  "setupPreference": "SELF",
  "confirmed": true
}
```

## 18–23. Validation and persistence

- The request middleware rejects every field outside `services`, `setupPreference`, and `confirmed`; protected IDs/module/status fields cannot be supplied.
- Service codes are normalized, deduplicated, parameter-bound, and resolved only from active service masters whose category and optional subcategory are active.
- Unknown, inactive, hidden-taxonomy, empty, and over-limit selections are rejected with safe field errors.
- Profile upsert, complete mapping replacement, and setup-step completion occur in one database transaction. Failure rolls back all three.
- Mapping updates delete only that provider's draft setup mappings and insert the new resolved selection. The operational priced mapping remains untouched.
- PUT is protected by the central required Idempotency-Key and Idempotency middleware. Database uniqueness also prevents duplicate profile/mapping rows.
- Completion requires at least one valid service, a valid preference, and explicit confirmation; only then is `home_service_setup` marked complete.

## 24–32. Progress and UX

- With every earlier required Home Service step complete, Phase 4E moves central progress to 100%.
- 100% means setup information is complete; it does not approve, activate, publish, or make the provider bookable.
- Next action is to return to Setup Center and review the complete setup. Full application submission remains out of Phase 4E.
- Existing dashboard/Setup Center behavior remains authoritative: 100% shows review-ready setup, not a live marketplace state.
- Frontend route: `/setup/home-service`.
- UX includes provider/category summary, searchable category/subcategory groups, 1–20 selection counter, SELF/ASSISTED cards, confirmation, Save & Return, Complete Setup, field errors, empty search state, unsaved-change warning, and a prominent not-live notice.
- Refresh GET restores canonical database selections, preference, confirmation, and progress.
- Ownership is derived from authenticated `PartnerContext`; no request ID controls ownership. Same-mobile Grocery/Home Service and Restaurant/Home Service contexts remain Partner-isolated.
- Grocery, Restaurant, Retail, Delivery, and non-Home-Service identities receive `PARTNER_SETUP_NOT_APPLICABLE` and create no Home Service rows.

## 33–36. Audit and side-effect boundaries

Audit events:

- `partner_setup.home_service_updated`
- `partner_setup.home_service_completed`
- `partner_setup.home_service_preference_changed` when applicable

Audit metadata contains service codes/count and setup preference only—no numeric owner IDs, prices, or protected state.

Phase 4E creates no rows in `home_service_provider_services`, no prices, bookings, staff, schedules, jobs, or marketplace records, and never changes provider approval, verification, operational availability, `marketplace_visible`, or active status.

## 37–40. Verification results

- Full Partner automated suite: **394 assertions passed**.
- PHP syntax: **all Partner PHP files passed** under PHP 8.0.30.
- Frontend lint: **passed with 2 pre-existing Fast Refresh warnings** in `PartnerSessionContext.jsx` and `PartnerSelectionContext.jsx`; no Phase 4E lint errors.
- Production build: **passed** with Vite 8.2.1.
- Exact build artifacts:
  - `frontend/dist/index.html`
  - `frontend/dist/assets/index-Cvt5DFD_.css`
  - `frontend/dist/assets/index-DLDYO3mB.js`

## 41–43. Exact production deployment

PHP files to upload:

1. `api/partner/Controllers/PartnerHomeServiceController.php`
2. `api/partner/Middleware/HomeServiceSetupRequestMiddleware.php`
3. `api/partner/Repositories/PartnerHomeServiceRepository.php`
4. `api/partner/Services/PartnerHomeServiceService.php`
5. `api/partner/Services/PartnerSetupService.php`
6. `api/partner/routes/protected.php`

Frontend files to deploy (replace the current built site with the complete `frontend/dist` output):

1. `frontend/dist/index.html`
2. `frontend/dist/assets/index-Cvt5DFD_.css`
3. `frontend/dist/assets/index-DLDYO3mB.js`

SQL/data instructions:

1. Back up `home_service_provider_profiles`, `home_service_masters`, `home_service_categories`, `home_service_subcategories`, and `schema_migrations`.
2. Run `database/migrations/phase_4e/000_preflight_home_service_setup.sql` against production. Stop if a required table is missing or duplicate `partner_id` rows are returned.
3. Run `database/migrations/phase_4e/001_home_service_setup.sql` once.
4. Run `database/verification/verify_phase_4e_home_service_setup.sql` and confirm the two profile columns, mapping keys/FKs, active service count, and migration marker.
5. No seed/master-data SQL is included. Production must already contain reviewed active Home Service master data; Phase 4E does not invent it.
6. `database/migrations/phase_4e/ROLLBACK.md` is recovery guidance, not a deployment script.

## 44–46. Blocker, risks, and next phase

The only production blocker is execution and verification of the additive Phase 4E migration before the new PHP route is used. Remaining operational risks are an empty active service master, a production schema that differs from the inspected Phase 21A contract, or deploying PHP before SQL. The repository fails safely with `PARTNER_HOME_SERVICE_SCHEMA_UNAVAILABLE` if required columns are absent.

After the verified migration, PHP upload, and frontend deployment, Phase 4E is ready for production smoke testing and the codebase is explicitly ready to begin **Phase 4F Delivery Partner Setup**. No continuation prompt is required for Phase 4E.

