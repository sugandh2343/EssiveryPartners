# Phase 4G Review and Submit schema reconciliation

`partner_onboarding_applications` remains the canonical application. It already contains owner exclusivity, public reference, module/identity, version, lifecycle status, percentage, current step, `submitted_at`, and current-row semantics. Review/Submit is therefore a lifecycle action, not a tenth Business step or seventh Delivery step.

The existing unique indexes `(business_partner_id,is_current)` and `(delivery_partner_id,is_current)` are safe because the Phase 3A constraint permits only `NULL` or `1`; multiple historical `NULL` rows do not collide in MySQL. Phase 4G does not introduce application version rows, so no index rewrite is warranted.

Admin expects `partners.onboarding_status` and `delivery_partners.onboarding_status` values including `submitted`, `under_review`, `correction_required`, `approved`, and `rejected`. Submission transactionally synchronizes only the owning record to `submitted`; the detailed canonical source remains `partner_onboarding_applications`. Approval and operational status remain untouched.

The only missing lifecycle structure is an application-specific transition history. `partner_status_history` is operational/domain-oriented and not safe to overload, so Phase 4G adds the narrow `partner_onboarding_status_history`. Audit remains separate and contains safe submission metadata.

