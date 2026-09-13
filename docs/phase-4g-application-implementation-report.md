# Essivery Partner — Phase 4G Review, Submit & Setup Completion

Implementation date: 2026-09-01

1. **Schema inspection findings.** Phase 3A already provides the canonical application, step rows, lifecycle status, percentage, version, `submitted_at`, and current-row semantics. Phase 4G adds only application-specific transition history.
2. **Canonical application owner/model.** `partner_onboarding_applications` owns the lifecycle. Exactly one of `business_partner_id` or `delivery_partner_id` identifies the owning Partner identity.
3. **Application lifecycle discovered.** The supported path is draft/in-progress → submitted → under-review → correction-required → resubmitted-as-submitted → approved/rejected. Submission does not perform review decisions.
4. **Admin compatibility findings.** Existing Admin logic recognizes submitted, under-review, correction-required, approved, and rejected onboarding states. Phase 4G synchronizes only the coarse owner `onboarding_status` to `submitted`.
5. **Delivery Admin compatibility findings.** Delivery submission uses the same canonical application lifecycle and synchronizes `delivery_partners.onboarding_status`; approval and availability remain unchanged.
6. **Current application uniqueness/index findings.** Existing unique indexes on `(business_partner_id,is_current)` and `(delivery_partner_id,is_current)` are retained. Historical rows may use `is_current=NULL`, which does not collide in MySQL.
7. **Migration required or not.** One additive migration is required for immutable onboarding transition history. No canonical application columns or indexes are replaced.
8. **Migration package if required.** The package is `database/migrations/phase_4g/000_preflight_review_submit.sql`, `001_review_submit.sql`, `ROLLBACK.md`, plus `database/verification/verify_phase_4g_review_submit.sql`.
9. **Application status model.** The API projects `SETUP_INCOMPLETE`, `READY_TO_SUBMIT`, `SUBMITTED`, `UNDER_REVIEW`, `CORRECTION_REQUIRED`, `APPROVED`, or `REJECTED` without making approval claims.
10. **Submission eligibility rules.** Every active required step must exist and have `step_status='complete'`. A stored 100% value alone is not accepted. The Partner must also send `confirmed=true`.
11. **Review API.** `GET /api/partner/setup/review` returns the server-composed, identity-scoped review model.
12. **Submit API.** `POST /api/partner/setup/submit` accepts only `confirmed`, requires `Idempotency-Key`, and derives all ownership and lifecycle data from authenticated context.
13. **Review response safe projection.** Public references and safe display fields are returned; internal database IDs, encrypted bank data, storage paths, internal notes, and review actor fields are excluded.
14. **Business section composition.** Business applications contain Personal, Business, Location, Business Hours, Operations, Documents, Bank Details, and the applicable module section.
15. **Delivery section composition.** Delivery applications contain Personal, Location, Documents, Bank Details, and Delivery Partner Setup only. Business-only sections are absent.
16. **Retail section.** The review shows the registration category, catalogue setup mode, and confirmation state; it creates no products, inventory, or catalogue.
17. **Restaurant section.** The review shows cuisines, menu setup mode, and confirmation state; it creates no menu items, prices, or orders.
18. **Home Service section.** The review shows selected setup services, setup preference, and confirmation state; it creates no pricing, staff, slots, or bookings.
19. **Delivery module section.** The review shows safe vehicle type, registration value when applicable, ownership, and confirmation state; it does not make the Partner online.
20. **Bank masking.** Only account-holder name, `•••• last4`, IFSC, and safe review state are projected. Full account number and ciphertext never leave the server.
21. **Document security.** Review returns opaque public document references and the existing authenticated file route. Filesystem/storage references and internal notes are not returned.
22. **Missing/correction issue projection.** Required incomplete or correction steps produce safe issues with a step code, generic Partner-facing message, and edit route.
23. **Transaction behavior.** Application status, first submission time, coarse owner status, and transition history are committed in one database transaction; any failure rolls back.
24. **Concurrency protection.** Submission locks the current application row with `FOR UPDATE` on MySQL before eligibility and lifecycle transition checks.
25. **Idempotency.** The endpoint requires the established idempotency middleware. The service also treats an already submitted/under-review application as a safe no-op and does not duplicate history.
26. **`submitted_at` behavior.** First submission sets it with `COALESCE`; repeated submission and correction resubmission preserve the original timestamp.
27. **Correction/resubmission behavior.** Correction reuses the same canonical application/version. Only requested correction sections can be edited; once complete, resubmission records a distinct `resubmission` transition.
28. **Lifecycle mutability guard.** `SetupMutabilityMiddleware` protects every setup write route. Submitted, under-review, approved, and rejected applications are read-only; correction-required applications permit only `needs_correction` sections. Bootstrap is also non-mutating in locked lifecycle states.
29. **100%-vs-submission separation.** Business remains nine required steps and Delivery remains six. Review & Submit is not a percentage-bearing step; 100% means ready, not submitted.
30. **Submission-vs-approval separation.** Submission changes onboarding lifecycle only. It does not approve, reject, verify, activate, or publish the Partner.
31. **Marketplace isolation.** No marketplace table or visibility flag is read or written by the review/submission repository.
32. **Referral isolation.** Submission does not claim referrals, credit rewards, or mutate referral leads.
33. **Wallet isolation.** Submission does not create wallet, ledger, settlement, payout, or balance activity.
34. **Setup Center integration.** At 100% the Setup Center exposes `Review & Submit`; submitted states expose `View Submission`; correction state exposes correction review.
35. **Dashboard integration.** The Dashboard links ready/submitted/correction states to `/setup/review` and displays Under Review without implying approval or activation.
36. **Frontend Review route.** Protected route `/setup/review` is registered before the generic `/setup/:step` route.
37. **Review UX.** The responsive page includes lifecycle header, completion, issue list, section cards, safe data, edit links, explicit confirmation, confirmation dialog, retry, and API error states.
38. **Submitted UX.** Cards remain visible read-only, the action area changes to `Submitted for Review`, and the safe submitted timestamp is displayed.
39. **Correction UX.** Correction cards are highlighted, non-requested sections have no edit action, requested sections return to Review after save/cancel, and the CTA becomes `Resubmit for Review` when eligible.
40. **Same-mobile identity isolation.** All reads and transitions use the authenticated Partner context plus the exact business/delivery owner key. Automated tests verify Business submission cannot submit the same-mobile Delivery application.
41. **Full automated assertion count.** `php api/partner/tests/run.php` passes **469 assertions**.
42. **PHP syntax result.** All new/changed Phase 4G PHP files pass `php -l` under local PHP 8.0.30; the production target remains PHP 8.3.
43. **Frontend lint result.** `npm.cmd run lint` completes with no errors. Two pre-existing Fast Refresh warnings remain in Partner context provider files.
44. **Production build result.** `npm.cmd run build` succeeds with 1,899 transformed modules. Vite reports only the existing >500 kB chunk advisory.
45. **Exact PHP upload list.** Upload `api/partner/Controllers/PartnerReviewController.php`, `Middleware/SetupMutabilityMiddleware.php`, `Repositories/PartnerReviewRepository.php`, `Services/PartnerReviewService.php`, `Services/PartnerSetupService.php`, and `routes/protected.php`, preserving their paths.
46. **Exact frontend dist files.** Deploy the complete `frontend/dist` contents: `index.html`, `favicon.svg`, `icons.svg`, `default-partner.svg`, `assets/index-jdLltHbo.css`, and `assets/index-BIbNiiky.js`. Remove obsolete hashed assets only after the new `index.html` is live.
47. **Exact SQL execution order if applicable.** (1) Select `u676721746_essivery`; (2) run `000_preflight_review_submit.sql` and stop on any `MISSING - STOP` or 100%-with-incomplete result; (3) export a recoverable backup of `partner_onboarding_applications`, `partner_onboarding_steps`, `partners`, `delivery_partners`, `audit_logs`, and `schema_migrations`; (4) run `001_review_submit.sql` once; (5) run `verify_phase_4g_review_submit.sql`; (6) upload PHP; (7) deploy the complete frontend dist; (8) smoke-test Business and Delivery review, submit, refresh, repeat submit, correction, and identity switching.
48. **Production blocker if applicable.** Local implementation has no code blocker. Production is blocked until the preflight is clean, the backup is retained, the Phase 4G migration marker exists, PHP files are uploaded together, and the matching frontend dist is deployed.
49. **Remaining risks.** Production schema/data can differ from the inspected repository assumptions, so preflight is mandatory. No canonical Partner-visible correction-reason field was found; the UI deliberately uses generic safe correction messages rather than exposing internal review notes. The bundle-size advisory should be addressed later through route-level code splitting, outside onboarding correctness.
50. **Readiness statement.** PARTNER ONBOARDING DEVELOPMENT IS READY FOR FULL END-TO-END TESTING.

No Admin approval, catalogue, menu management, service pricing, orders, bookings, delivery jobs, wallet, settlement, referral reward, payout creation, or marketplace activation was implemented.
