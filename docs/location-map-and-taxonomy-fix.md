# Partner Location map and Lucknow taxonomy fix

## Root causes

1. The current frontend build has no `VITE_GOOGLE_MAPS_API_KEY`, so Google Maps cannot load.
2. `226028 / Lucknow / Uttar Pradesh` is absent from the canonical `pincodes → cities → states` taxonomy, so the API correctly refuses to persist unresolved foreign keys.

## Database execution order

1. Select `u676721746_essivery` in phpMyAdmin.
2. Run `database/migrations/phase_3d/002_preflight_lucknow_taxonomy.sql`.
3. Stop if a required table/column is missing, or if an existing Lucknow/226028 row points to a different state/city.
4. Back up `states`, `cities`, `pincodes`, and `schema_migrations`.
5. Run `database/migrations/phase_3d/003_seed_lucknow_taxonomy.sql` once.
6. Run `database/verification/verify_lucknow_location_taxonomy.sql` and require one active joined row plus the migration marker.

This is a deliberately narrow repair for the confirmed address. It is not a complete India geography import.

## Google Maps configuration

1. In Google Cloud, enable Maps JavaScript API and billing.
2. Create a browser API key and restrict it by HTTP referrer to `http://localhost:5173/*` and `https://partners.essivery.in/*`.
3. Add `VITE_GOOGLE_MAPS_API_KEY=...` to the frontend build environment. A Vite variable is compiled into the build; adding it to the server after building does not modify an existing bundle.
4. Optionally set `VITE_GOOGLE_MAPS_MAP_ID`; it is not required for the current standard draggable marker.
5. Run `npm.cmd run build` and deploy the complete new `frontend/dist`.

The browser key is necessarily visible to clients; security comes from API restrictions and HTTP-referrer restrictions, not from treating the browser key as a server secret.
