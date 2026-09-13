# Phase 4F — Delivery Partner Setup complete report

**Date:** 2026-09-01  
**Status:** Phase 4F application implementation is complete locally.

> **PRODUCTION DEPLOYMENT BLOCKED UNTIL THE PHASE 4F PREFLIGHT, MIGRATION, AND VERIFICATION ARE COMPLETED.**

## 1–13. Schema, ownership, vehicle, and document decisions

The canonical Delivery schema was inspected in the Admin full schema, Delivery recommendations, Delivery Admin repository/UI, Partner setup engine, document subsystem, and local tests.

1. `delivery_partners` is the canonical Delivery identity; it already owns status, approval, offline availability, and location.
2. Ownership is exclusively `delivery_partners.id`. Phase 4F never creates or resolves through a merchant `partners.id`.
3. `delivery_partner_vehicles.delivery_partner_id` is the canonical target vehicle architecture. Production preflight subsequently confirmed that this table is absent there, so the corrected 001 migration creates it safely before applying the Phase 4F extensions. One active primary vehicle is edited for onboarding; no multi-vehicle UI was added.
4. No Delivery vehicle master existed. Admin hardcoded seven legacy codes, so Phase 4F creates `delivery_vehicle_types` using those exact codes and server-owned requirement flags.
5. Ownership is stored as one of `OWNED`, `FAMILY`, `RENTED`, or `COMPANY_PROVIDED`; free text is rejected.
6. Bicycle needs no registration. Motorcycle, scooter, electric scooter, car, mini truck, and other registered vehicle require registration. Values are uppercased with spaces/hyphens removed and checked against conventional Indian or BH-series formats. This is format validation, not government verification.
7. Registered motor vehicles require `DRIVING_LICENCE_FRONT`; Bicycle does not.
8. Registered motor vehicles require `VEHICLE_RC_FRONT`; Bicycle does not.
9. `VEHICLE_INSURANCE` is shown as optional for registered vehicles. It is not required for Phase 4F completion.
10. Delivery documents remain in `delivery_partner_documents`, using Phase 4A private storage, opaque references, MIME/fileinfo validation, replacement history, and review states.
11. Migration is required for the vehicle master, ownership, explicit setup confirmation, and deterministic one-current-vehicle uniqueness.
12. The package contains preflight, additive migration/master seed, verification, rollback guidance, and schema reconciliation report.
13. GET derives registration, DL, RC, and insurance requirements from active master metadata. The frontend never guesses them.

Back-side licence/RC files, structured document numbers, vehicle photos, service radius, schedules, live GPS, online state, jobs, earnings, and approval are deferred.

## 14–18. Backend and API

New backend files:

- `api/partner/Controllers/PartnerDeliveryController.php`
- `api/partner/Middleware/DeliverySetupRequestMiddleware.php`
- `api/partner/Repositories/PartnerDeliveryRepository.php`
- `api/partner/Services/PartnerDeliveryService.php`

Modified backend files:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/Services/PartnerDocumentsService.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php` (local coverage only)

Routes:

- `GET /api/partner/setup/delivery`
- `PUT /api/partner/setup/delivery`
- Existing `POST /api/partner/setup/documents` now permits the three Delivery vehicle document codes only for an authenticated Delivery identity.
- Existing authenticated `GET /api/partner/setup/documents/{publicDocumentId}/file` remains the private preview route.

GET returns the safe Delivery name, current vehicle code/name, authenticated full registration, ownership, dynamic requirements, applicable document cards/states, remaining requirements, step, and central setup summary. No numeric IDs, raw paths, approval fields, or availability fields are exposed.

PUT accepts only:

```json
{
  "vehicleType": "motorcycle",
  "vehicleRegistrationNumber": "UP32AB1234",
  "vehicleOwnership": "OWNED",
  "confirmed": true
}
```

## 19–29. Validation, resuming, transactions, and regressions

19. The strict allowlist rejects every unknown/protected field, including Delivery/Partner/User IDs, module/type, approval, online/availability, wallet, status, timestamps, and completion percentage.
20. Vehicle details can be saved with `confirmed=false`; the row and values persist while `delivery_setup` remains `in_progress`. Missing required vehicle documents also permits a successful partial save.
21. Delivery document codes are server-allowed only for Delivery identity. Business Partners cannot upload DL/RC/insurance through this route.
22. Files remain private, limited to JPEG/PNG/WebP/PDF and 5 MB, and are streamed only after owner-scoped authentication using opaque public references.
23. Vehicle resolution, validation, current-vehicle upsert, confirmation update, requirement recalculation, step-state synchronization, and progress recalculation run in one transaction. Document uploads remain separate secure transactions.
24. PUT requires the shared Idempotency-Key and Idempotency middleware. The unique current-vehicle key prevents duplicate active primary rows; repeated selection saves update the same record.
25. Completion requires an active vehicle type, valid registration when required, valid ownership, pending/approved required vehicle documents, and explicit confirmation. Admin approval is not required.
26. Delivery uses the central six-step definition. Completion produces server-calculated 6/6 and 100%; no percentage is hardcoded.
27. 100% does not approve, verify, put online, enable jobs, activate earnings, or enable payout.
28. Changing Bicycle to a motor vehicle immediately adds DL/RC requirements and regresses the step until satisfied. Changing back to Bicycle ignores irrelevant vehicle documents without deleting their history.
29. Required DL/RC correction/rejection changes `delivery_setup` to `needs_correction`; replacement pending or approved restores data-entry eligibility. Common Aadhaar/PAN corrections continue to regress the separate common `documents` step.

## 30–39. Product behavior and boundaries

30. After 100%, the next state is Review Setup/Submit preparation; Phase 4F does not submit.
31. Delivery dashboard copy is now: “Delivery Partner setup information is complete,” with explicit not-approved/not-online/not-job-ready clarification.
32. Frontend route: `/setup/delivery`.
33. Mobile-first UX includes large vehicle cards, conditional registration, ownership cards, camera-friendly document upload, private previews, document status/replacement, clear remaining requirements, confirmation, Save & Return, and Complete Setup.
34. Refresh GET restores the canonical vehicle, normalized registration, ownership, confirmation, requirements, document states, step, and progress.
35. Ownership always comes from Delivery `PartnerContext`. Same-mobile merchant + Delivery identities remain isolated.
36. Retail, Restaurant, Home Service, other business identities, and Customer access are rejected by existing auth/context plus Delivery applicability checks. No Delivery rows are created for them.
37. Safe audit events: `partner_setup.delivery_updated`, `partner_setup.delivery_completed`, and `partner_setup.delivery_vehicle_changed`. Existing document audit semantics are reused. Registration contents and internal IDs are not audited.
38. Phase 4F creates no job, assignment, route, tracking session, or online/availability side effect.
39. It creates no wallet credit, settlement, payout, bank mutation, or payout-eligibility change; approval remains pending and availability remains offline.

## 40–43. Verification results

40. Full Partner regression: **425 assertions passed**, including all prior Phase 4E coverage.
41. PHP syntax: **all Partner PHP files passed** under PHP 8.0.30.
42. Frontend lint: **passed with two pre-existing Fast Refresh warnings** in `PartnerSessionContext.jsx` and `PartnerSelectionContext.jsx`; no Phase 4F lint errors.
43. Vite 8.2.1 production build: **passed**. A non-blocking >500 kB chunk advisory remains.

## 44–46. Exact deployment list and SQL order

PHP files to upload:

1. `api/partner/Controllers/PartnerDeliveryController.php`
2. `api/partner/Middleware/DeliverySetupRequestMiddleware.php`
3. `api/partner/Repositories/PartnerDeliveryRepository.php`
4. `api/partner/Services/PartnerDeliveryService.php`
5. `api/partner/Services/PartnerSetupService.php`
6. `api/partner/Services/PartnerDocumentsService.php`
7. `api/partner/routes/protected.php`

Frontend deployment files (deploy the complete new `frontend/dist` and remove references to superseded hashed assets):

1. `frontend/dist/index.html`
2. `frontend/dist/assets/index-BJ8Dym3S.css`
3. `frontend/dist/assets/index-D0w0Ftuq.js`

SQL/config/storage instructions:

1. Back up `delivery_partners`, `delivery_partner_vehicles`, `delivery_partner_documents`, and `schema_migrations`.
2. Run the corrected `database/migrations/phase_4f/000_preflight_delivery_setup.sql`. `delivery_partner_vehicles = MISSING - 001 WILL CREATE` is acceptable. Stop for any other missing required table or any duplicate active-primary rows. Review legacy vehicle codes when present.
3. Run `database/migrations/phase_4f/001_delivery_setup.sql` once. This includes the seven-row canonical vehicle-type master seed.
4. Run `database/verification/verify_phase_4f_delivery_setup.sql`. Confirm all columns/indexes, active master rules, zero duplicate current vehicles, and the migration marker.
5. Upload the seven PHP files.
6. Deploy the complete frontend `dist`.
7. Smoke-test Bicycle partial/final save, Motorcycle registration, DL/RC upload/private preview, refresh, correction replacement, and 100%-without-activation.
8. No new storage path or environment variable is required. Continue using the existing private `ESSIVERY_PARTNER_DOCUMENT_STORAGE` configuration and ensure it remains writable/non-public.
9. `database/migrations/phase_4f/ROLLBACK.md` is recovery guidance, not a deployment script.

## 47–49. Blocker, risks, and next phase

47. Production is blocked only until the preflight/migration/verification are completed before the new PHP route is used.
48. Remaining risks: legacy duplicate active-primary vehicles in environments where the table exists, legacy vehicle codes outside the inspected Admin list, another required production table being absent, and deploying PHP before SQL. The corrected migration now handles the confirmed missing vehicle table; preflight surfaces the remaining cases. The repository fails safely with `PARTNER_DELIVERY_SCHEMA_UNAVAILABLE` when the target schema is absent. The existing frontend bundle-size advisory should be addressed later through route-level code splitting.
49. After verified SQL, PHP/config upload, frontend deployment, and Delivery smoke testing, the application is explicitly ready for **Phase 4G Review, Submit & Setup Completion**. No continuation prompt is required for Phase 4F.
