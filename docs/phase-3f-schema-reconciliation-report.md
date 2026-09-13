# Essivery Partner Phase 3F — Operations schema reconciliation

Date: 2026-08-30  
Decision: **schema gate triggered; application implementation intentionally stopped**

## Existing schema inspected

The audit covered the canonical Phase 17 marketplace migration, Partner setup code and fixtures, Admin marketplace repository/UI, User readiness/referral consumers, Restaurant Phase 20 schema, Home Service Phase 21 schema, Delivery configuration, and all local references to fulfilment, pickup, self-delivery, Essivery delivery, preparation time, minimum order, radius, and delivery fees.

`partner_marketplace_settings` currently owns Partner-wide marketplace presentation/readiness and limited commercial settings. It is uniquely keyed only by `partner_id` and is read/upserted without `module_code` by existing Admin/User code. It contains no Essivery Delivery, Self Delivery, Customer Pickup, or Home Service delivery-mode fields.

`restaurants` contains Restaurant-specific minimum order, delivery fee, and default preparation time, but does not provide the common fulfilment-mode contract. `home_service_provider_profiles` owns provider governance/booking defaults and has no customer/provider-location mode. Delivery tables describe fulfilment providers and are not merchant Operations ownership.

## Canonical ownership decision

Changing `partner_marketplace_settings` to multiple module rows is unsafe: its existing `UNIQUE(partner_id)` and unscoped readers/upsert would break, return ambiguous settings, or overwrite another authenticated module. Adding fulfilment flags to its single row would still cause Grocery/Restaurant cross-module leakage.

The minimal safe owner is therefore a focused module-scoped extension table, `partner_fulfilment_settings`, keyed uniquely by `(partner_id,module_code)`. This is not a second marketplace/readiness architecture: it owns only setup preference A (how the business fulfils), while `partner_marketplace_settings` continues to own its existing marketplace/readiness fields unchanged.

Minimum fields required:

- Retail/Restaurant: `essivery_delivery`, `self_delivery`, `customer_pickup`
- Home Service: `service_at_customer_location`
- Ownership/scope: `partner_id`, `module_code`, one active canonical row

`service_at_provider_location` is deferred because the existing Home Service model does not establish that product concept. Preparation time remains Restaurant-owned and is deferred from the common Operations form. Minimum order and delivery radius remain existing marketplace/Restaurant commercial/serviceability concerns and are also deferred. No delivery fee, fake radius, live status, approval, visibility, service area, or Delivery Partner assignment field is introduced.

## Compatibility

The migration is additive. It does not alter or populate `partners`, `partner_business_modules`, `partner_marketplace_settings`, Restaurant, Home Service, Delivery, User/Admin authentication, setup, referral, wallet, or marketplace tables. Existing consumers remain unchanged. It inserts no Partner fulfilment rows; explicit confirmation will be required by the future Phase 3F application.

The foreign key requires canonical `partners.id` to be `BIGINT UNSIGNED` and InnoDB. The preflight checks this and stops if the candidate table already exists. Review the full preflight output before migration.

## Package and execution order

1. Run `database/migrations/phase_3f/000_preflight_operations.sql` (read-only).
2. Review table definitions, key type, and candidate-table result.
3. Take a database backup.
4. Run `database/migrations/phase_3f/001_partner_fulfilment_settings.sql` once.
5. Run `database/verification/verify_phase_3f_operations.sql` (read-only).
6. Confirm all checks pass and retain the output.

No Phase 3F backend route, service, repository, controller, setup-completion method, frontend page, or test fixture was implemented because the specification explicitly requires schema approval first.
