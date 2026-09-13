# Essivery Partner Phase 3E — Business Hours implementation report

Date: 2026-08-30

## Outcome

Phase 3E application support is implemented against the operator-applied normalized `partner_business_hours` schema. No new production migration was created or executed. The implementation supports seven explicit weekdays, closed days, multiple slots, overnight slots, explicit 24-hour operation, frontend copy actions, transaction-safe weekly replacement, setup progress, and Delivery exclusion.

## Schema verification

The local authoritative migration and verification package was inspected:

- `database/migrations/phase_3e/001_business_hours_normalization.sql`
- `database/verification/verify_phase_3e_business_hours.sql`

The expected application columns are `partner_id`, `module_code`, `day_of_week`, `slot_order`, `is_open`, `is_closed`, `is_24_hours`, `is_overnight`, `opens_at`, `closes_at`, and `status`. The expected unique schedule key is `(partner_id,module_code,day_of_week,slot_order)`. The SQLite test fixture was updated to this model. Production was not queried or modified by Codex.

## Backend delivery

Created:

- `api/partner/Middleware/BusinessHoursRequestMiddleware.php`
- `api/partner/Repositories/PartnerBusinessHoursRepository.php`
- `api/partner/Services/PartnerBusinessHoursService.php`
- `api/partner/Controllers/PartnerBusinessHoursController.php`

Modified:

- `api/partner/Services/PartnerSetupService.php`
- `api/partner/routes/protected.php`
- `api/partner/tests/run.php`

Routes:

- `GET /api/partner/setup/hours`
- `PUT /api/partner/setup/hours`

Both routes use the existing JWT, identity, Partner rate-limit, and PartnerContext middleware chain. PUT also uses the Business Hours request allowlist and existing idempotency middleware. Authentication and multi-identity code were not modified.

The server derives module scope from authenticated `PartnerContext`: retail identities use `RETAIL`, restaurant uses `RESTAURANT`, and Home Service uses `HOME_SERVICE`. The client cannot choose a module or submit internal IDs/status/progress fields.

## Canonical persistence

- Closed day: one row at slot 1; `is_open=0`, `is_closed=1`, other state flags false, both times null.
- 24 hours: one row at slot 1; `is_open=1`, `is_24_hours=1`, other state flags false, both times null.
- Ordinary day: one row per validated slot, with deterministic server-assigned slot order.
- Overnight: explicit `is_overnight=1` and closing time earlier than opening time.
- New rows always use `status='active'`.

PUT validates the complete payload before opening persistence work. Within one database transaction it deletes the existing Partner+module schedule, inserts the normalized replacement, marks the setup step complete, recalculates progress, and writes audit events before commit. Any failure rolls back the complete operation. A new idempotency key replaces rather than appends. The same key is handled by the existing idempotency middleware.

Validation covers exactly seven known and unique weekdays, strict `HH:MM`, mutually exclusive day states, at least one open day, ordinary/overnight semantic consistency, duplicate/overlapping same-day intervals, protected fields, and a maximum of eight slots per day. Existing ambiguous normalized rows return a controlled `PARTNER_HOURS_DATA_INVALID` response instead of guessed semantics.

After a valid business save, `hours` becomes complete through `PartnerSetupService`; progress remains server-derived (normally 5/9 = 56%), and `nextRecommendedStep` centrally resolves to `operations`. Delivery definitions still contain no Hours step, direct API access returns `PARTNER_SETUP_NOT_APPLICABLE`, and no Delivery hours rows are written.

Audit events:

- `partner_setup.hours_updated`, with changed weekdays and active-slot count
- `partner_setup.hours_completed`

## Frontend delivery

Created:

- `frontend/src/pages/BusinessHoursPage.jsx`

Modified:

- `frontend/src/services/partner/partnerSetupService.js`
- `frontend/src/App.jsx`

The protected `/setup/hours` page restores official saved hours through GET. For a new schedule it displays an unsaved 09:00–21:00 suggestion. It provides Open, Closed, Open 24 Hours, add/remove slot, Overnight, copy-to-all, weekdays, and weekend actions. It shows field errors, tracks unsaved edits, warns on browser unload, updates the setup cache after save, and follows backend next-step metadata. Delivery direct navigation is redirected back to Setup Center after the controlled not-applicable response.

## Verification results

- PHP syntax: all Partner PHP files passed.
- Partner automated suite: **196 assertions passed**.
- Frontend lint: passed with two pre-existing Fast Refresh warnings in Partner context files.
- Production frontend build: passed.
- Build artifacts: `frontend/dist/index.html`, `frontend/dist/assets/index-BpcfYs6R.css`, `frontend/dist/assets/index-BZ7B9YDQ.js`.

Production runtime/API smoke testing was not performed from this workspace.

## Deployment

Upload these PHP files, preserving their relative paths:

1. `api/partner/Middleware/BusinessHoursRequestMiddleware.php`
2. `api/partner/Repositories/PartnerBusinessHoursRepository.php`
3. `api/partner/Services/PartnerBusinessHoursService.php`
4. `api/partner/Controllers/PartnerBusinessHoursController.php`
5. `api/partner/Services/PartnerSetupService.php`
6. `api/partner/routes/protected.php`

Tests and documentation are not production runtime files. Upload the complete contents of `frontend/dist/` to the Partner frontend document root and remove obsolete hashed assets only after the new `index.html` is live.

## SQL instructions

Run **no new SQL**. The operator already applied Phase 3E normalization. Optionally re-run only the read-only `database/verification/verify_phase_3e_business_hours.sql` to retain evidence of the deployed schema.

## Smoke test

1. Log in as Grocery and open Setup Center.
2. Open Business Hours; confirm seven suggested days are visible and not yet persisted.
3. Configure Monday, copy it to all, close one weekday, save, and refresh.
4. Confirm the saved schedule restores, Hours is complete, progress is server-derived, and Operations is next.
5. Save two Restaurant slots (`11:00–15:00`, `18:00–02:00` with Overnight enabled); refresh and confirm both restore.
6. Confirm overlapping slots return a friendly validation error and do not partially replace the saved week.
7. Confirm Open 24 Hours restores with no visible time slots.
8. Log in as Delivery; confirm Setup Center has no Hours card and direct `/setup/hours` returns to Setup Center without writing rows.
9. Confirm Dashboard/Setup Center reflect the updated setup cache and remain correct after a browser refresh.

## Remaining risk and readiness

The remaining deployment risk is environment verification: the production table/index must match the already supplied verification SQL, and the live shared idempotency/audit tables must be healthy. No User/Admin authentication, Firebase, referral, wallet, approval, marketplace visibility, serviceability, or operational-status behavior was changed.

Subject to the upload and smoke test above, Phase 3E is application-complete and ready for Phase 3F.
