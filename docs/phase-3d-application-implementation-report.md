# Phase 3D Location Application Implementation Report

Date: 2026-08-30

## Status

Phase 3D application implementation is complete in the workspace and locally verified. Production deployment and authenticated smoke testing remain operator actions.

## API and ownership

- `GET /api/partner/setup/location`
- `PUT /api/partner/setup/location`
- JWT, Partner identity, rate limit and `PartnerContext` middleware apply to both.
- PUT additionally requires a valid `Idempotency-Key` and strict body allowlisting.
- Business identities write only to `partners`.
- Delivery writes only to `delivery_partners`.
- No store, restaurant, provider, service-area or Delivery-zone rows are created.

## Canonical mappings

Business: `address_line_1`, `address_line_2`, `locality`, `landmark`, `pincode`, `city_id`, `state_id`, `latitude`, `longitude` on `partners`.

Delivery: `address_line`, `address_line_2`, `locality`, `landmark`, `pincode`, `city_id`, `state_id`, `latitude`, `longitude` on `delivery_partners`.

City and state names are returned through safe joins; numeric IDs are not exposed.

## Validation and taxonomy

- Address Line 1, locality, pincode, city, state, latitude, longitude and explicit map confirmation are required.
- Indian pincode uses `^[1-9]\d{5}$`.
- Coordinates are stored at seven decimal places after range validation.
- Known active pincodes resolve through `pincodes → cities → states`; conflicting submitted city/state is rejected.
- Unknown but syntactically valid pincodes may save when city/state resolve to an active canonical pair.
- Unknown pincodes do not imply marketplace serviceability.
- Unknown and protected request fields return controlled 422 validation errors.

## Referral and serviceability

- Referral address values are returned only when no official Location exists.
- Saved official values always suppress referral suggestions.
- Location writes never mutate referral records.
- `serviceabilityStatus` is conservatively returned as `notConfigured`.
- No service-area or zone assignments occur.

## Setup integration

- Valid explicit save marks the centralized `location` step complete.
- Progress is recalculated by `PartnerSetupService`; no frontend percentage is accepted.
- Current Retail flow reaches 44% and recommends Hours.
- Current Delivery flow reaches 50% after mobile, personal and location, and recommends Documents.
- Updated setup summary is cached immediately for Dashboard and Setup Center synchronization.

## Map and fallback

- `/setup/location` uses optional browser geolocation.
- Google Maps loads only when `VITE_GOOGLE_MAPS_API_KEY` is configured.
- Optional Map ID, region and language follow existing User conventions.
- Map clicking and marker dragging update coordinates and attempt reverse geocoding.
- Reverse-geocoded fields fill only empty inputs.
- Pincode disagreement produces a warning and never silently replaces input.
- Manual address and coordinate entry remains available when Maps/geolocation fails or permission is denied.

## Audit

The canonical update, setup completion, progress calculation and these minimized audit events share one transaction:

- `partner_setup.location_updated`
- `partner_setup.location_completed`

Only changed field names are audited; exact addresses and coordinates are not copied into metadata.

## Verification

- PHP syntax: pass.
- Partner suite: 158 assertions pass.
- Frontend lint: pass with two pre-existing Fast Refresh warnings only.
- Vite production build: pass.

Production bundle:

- `dist/index.html`
- `dist/assets/index-DpsWLIhU.css`
- `dist/assets/index-Wjv7KcMN.js`

## Deployment

Upload all new/modified Partner PHP files listed in the delivery response, then deploy the complete latest `frontend/dist` atomically. No additional SQL is required after the already-applied Phase 3D reconciliation migration.

Configure the Partner frontend environment with the existing Google Maps browser-key conventions if the interactive map is desired. Without a key, manual setup remains functional.

## Remaining production checks

- Run the Phase 3D schema verification SQL and confirm PASS.
- Authenticated GET/PUT smoke test for one business identity and one Delivery identity.
- Confirm Google API key allows `partners.essivery.in` and required Maps APIs.
- Confirm Hostinger serves the new hashed frontend assets.

The canonical Phase 2A model has one business `partners` row per `users.id`. If future product behavior requires multiple independent business locations for multiple business identities under one single User row, that is a cross-phase ownership-model decision and must not be solved with a Phase 3D fallback table.

