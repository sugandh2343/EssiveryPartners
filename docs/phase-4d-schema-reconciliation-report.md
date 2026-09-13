# Phase 4D Restaurant schema reconciliation

`partners.id` remains the authenticated Business Partner context and `partner_business_modules` remains module membership. Production inspection established that the operational Restaurant profile is canonically owned by `restaurants.owner_user_id -> users.id`; PartnerContext safely bridges this through the uniquely owned Partner's `user_id`. The production inventory shows an existing `cuisines` master and existing `restaurant_cuisines` natural mapping; no repository evidence establishes canonical Restaurant type or VEG/NON_VEG classification consumption. Those classifications are therefore deferred rather than invented.

Phase 4D minimally extends the canonical `restaurants` profile with menu preference, explicit confirmation, and deterministic active-owner uniqueness, and reuses normalized `restaurant_cuisines(restaurant_id,cuisine_id)` mappings to the existing cuisine master. Setup-created rows remain `status='inactive'` and `operational_status='closed'`. No cuisine names are hardcoded or seeded. Production must already contain reviewed active cuisine master data.

FSSAI/GST, preparation time, minimum order, packaging charges, media, menu/dishes, pricing, inventory, runtime/order state, approval, and marketplace activation are deferred. Existing Restaurant rows do not auto-complete setup; explicit confirmation is required.
