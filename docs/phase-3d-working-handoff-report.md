# Essivery Partner Phase 3D Working Handoff Report

Date: 2026-08-30  
Scope: Location schema reconciliation only  
Production database: `u676721746_essivery`

## Executive status

Phase 3D is **schema-ready but not application-complete**.

Completed:

- authoritative location ownership audit;
- production preflight;
- additive canonical schema migration;
- migration registration command;
- verification and rollback package;
- preservation of User, Admin, PartnerContext, authentication, referral and marketplace behavior.

Not implemented under the schema-reconciliation prompt:

- `GET /api/partner/setup/location`;
- `PUT /api/partner/setup/location`;
- Location repository/service/controller/middleware;
- `/setup/location` frontend page;
- browser geolocation or Google Maps integration;
- referral location prefill;
- Location step completion/progress update;
- Phase 3D application tests and deployment build.

Do not describe Phase 3D as fully complete until these application items are implemented and tested.

## Authoritative schema findings

### Business Partners

`partners` is the canonical physical/postal address owner for Retail, Restaurant and Home Service Partners.

Current Location target fields:

| API field | Canonical column |
|---|---|
| `addressLine1` | `partners.address_line_1` |
| `addressLine2` | `partners.address_line_2` |
| `locality` | `partners.locality` |
| `landmark` | `partners.landmark` |
| `city` | display resolved through `partners.city_id → cities.id` |
| `state` | display resolved through `partners.state_id → states.id` |
| `pincode` | `partners.pincode` |
| `latitude` | `partners.latitude` (`DECIMAL(10,7)`) |
| `longitude` | `partners.longitude` (`DECIMAL(10,7)`) |

### Delivery Partners

`delivery_partners` remains the exclusive physical/base address owner for Delivery identities.

| API field | Canonical column |
|---|---|
| `addressLine1` | `delivery_partners.address_line` |
| `addressLine2` | `delivery_partners.address_line_2` |
| `locality` | `delivery_partners.locality` |
| `landmark` | `delivery_partners.landmark` |
| `city` | display resolved through `delivery_partners.city_id → cities.id` |
| `state` | display resolved through `delivery_partners.state_id → states.id` |
| `pincode` | `delivery_partners.pincode` |
| `latitude` | `delivery_partners.latitude` (`DECIMAL(10,7)`) |
| `longitude` | `delivery_partners.longitude` (`DECIMAL(10,7)`) |

Delivery Location must never create a `partners`, `grocery_stores`, `restaurants`, or Home Service profile row.

## Module projection decisions

- `grocery_stores` is a Retail marketplace/store projection, not the complete postal-address owner.
- `restaurants` is a Restaurant marketplace projection and lacks the complete structured postal contract.
- `home_service_provider_profiles` contains provider-operational data, not a base postal address.
- `partner_service_areas`, `grocery_store_service_areas`, `restaurant_service_areas`, and Delivery zone mappings represent serviceability, not physical location.
- No module rows or service-area mappings were created by the migration.
- No approval, operational, marketplace-visible or serviceability status was changed.

## Production migration

Migration version:

`2026_08_30_3d_location_columns`

Added nullable columns:

```sql
partners.locality                     VARCHAR(150) NULL
delivery_partners.address_line_2      VARCHAR(255) NULL
delivery_partners.locality            VARCHAR(150) NULL
```

The migration is additive and uses `ADD COLUMN IF NOT EXISTS`. It does not rename, drop, copy or update row data.

Production execution status: the operator confirmed the migration and registration were run. This repository cannot independently query the Hostinger database, so the post-migration verification SQL remains the authoritative final check.

## Migration package

- `database/migrations/phase_3d/000_preflight_location_columns.sql`
- `database/migrations/phase_3d/001_location_persistence_columns.sql`
- `database/verification/verify_phase_3d_location_columns.sql`
- `database/migrations/phase_3d/ROLLBACK.md`

The scripts use fully qualified production table names because Hostinger phpMyAdmin changed execution context to `information_schema` during earlier metadata queries.

## Required final production verification

Run `database/verification/verify_phase_3d_location_columns.sql` and confirm:

1. `database_check = PASS`;
2. `required_columns_check = PASS`;
3. exactly three expected new column definitions;
4. preflight and post-migration Partner row counts match;
5. exactly one `schema_migrations` row for `2026_08_30_3d_location_columns`;
6. `SHOW CREATE TABLE` contains the new nullable columns.

Expected definitions:

```text
partners.locality                     varchar(150) YES
delivery_partners.address_line_2      varchar(255) YES
delivery_partners.locality            varchar(150) YES
```

## Compatibility assessment

- Production Partner bootstrap inserts use explicit column lists and remain compatible.
- No ownership index or foreign key was changed.
- Existing rows receive `NULL` in the new fields.
- Admin and User schemas remain readable.
- Firebase authentication and multi-identity User logic were untouched.
- `PartnerContext` was untouched.
- Referral history and claim behavior were untouched.
- Existing marketplace/serviceability repositories were untouched.
- Existing Partner automated suite still passes: **137 assertions**.

The SQLite fixtures contain positional inserts, but their local test tables have not yet been expanded. They must be updated together with Phase 3D application tests.

## Map and taxonomy plan for the pending implementation

- Reuse `VITE_GOOGLE_MAPS_API_KEY`, `VITE_GOOGLE_MAPS_MAP_ID`, `VITE_GOOGLE_MAPS_REGION`, and `VITE_GOOGLE_MAPS_LANGUAGE`; never hardcode keys.
- Browser geolocation must remain optional.
- Manual address and coordinate entry must work when geolocation is denied or Maps is unavailable.
- Resolve known pincodes through `pincodes → cities → states`.
- A syntactically valid but unsupported pincode must not automatically block physical-location saving.
- Do not silently replace a Partner-entered pincode after reverse geocoding.
- `mapConfirmed` should be represented by explicit successful Location-step completion, not a new core-table column.

## Pending Phase 3D implementation contract

The next implementation must add:

- `GET /api/partner/setup/location`;
- `PUT /api/partner/setup/location` with `Idempotency-Key`;
- strict input allowlisting and ownership from `PartnerContext` only;
- canonical city/state resolution;
- decimal coordinate validation;
- referral suggestions only when official fields are empty;
- server-side `markLocationComplete` and progress recalculation;
- Retail progress determined by the server (expected 33% → 44% with nine required steps);
- Delivery next step derived centrally (currently Documents);
- audit events `partner_setup.location_updated` and `partner_setup.location_completed`;
- `/setup/location` responsive UI, current-location fallback and map-confirmation UX;
- application, browser-state, refresh and identity-isolation tests.

## Readiness decision

Schema reconciliation is complete subject to the final verification query.

Phase 3E should **not** assume Location setup is complete. The safe next task is the Phase 3D application implementation using this reconciled schema. Phase 3E may begin only after the Location routes, persistence, UI, progress behavior and tests are complete.

