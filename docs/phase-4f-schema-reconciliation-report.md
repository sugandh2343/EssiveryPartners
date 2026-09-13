# Phase 4F Delivery schema reconciliation

The canonical owner is `delivery_partners.id`. The existing `delivery_partner_vehicles.delivery_partner_id` and `delivery_partner_documents.delivery_partner_id` relationships are retained; no merchant `partners` row or parallel Delivery profile is introduced.

The local/Admin reference schema defines `delivery_partner_vehicles`, but production preflight proved that the table is currently absent. The corrected migration therefore creates that canonical Delivery-owned table when missing, using the reviewed Admin fields, and then adds constrained ownership, explicit setup confirmation, and deterministic one-current-vehicle protection. If a future/other environment already has the table, `CREATE TABLE IF NOT EXISTS` preserves it and only the additive Phase 4F columns/index are applied. Preflight checks legacy duplicates only when the table exists.

No Delivery vehicle-type master exists. Admin currently hardcodes `bicycle`, `motorcycle`, `scooter`, `electric_scooter`, `car`, `mini_truck`, and `other`. Phase 4F normalizes those exact existing codes in `delivery_vehicle_types` and adds server-owned requirement metadata. Bicycle requires neither registration, driving licence, nor RC. Registered motor vehicles require registration, driving licence, and RC. Insurance is optional in Phase 4F.

Vehicle documents reuse `delivery_partner_documents` and the Phase 4A private storage/opaque-reference/review-state pipeline. Phase 4F uses `DRIVING_LICENCE_FRONT` and `VEHICLE_RC_FRONT` when required and exposes `VEHICLE_INSURANCE` as optional for registered vehicles. Back-side images, structured licence/insurance numbers, vehicle photos, live availability, jobs, routing, approval, and payouts remain deferred.
