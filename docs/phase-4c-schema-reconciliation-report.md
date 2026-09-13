# Phase 4C Retail Setup schema reconciliation

The canonical Retail module membership remains `partner_business_modules`; the canonical Partner-to-Retail-category association remains `partner_retail_parent_categories`. The authenticated identity's `parentCategoryId` and `PartnerContext` determine the exact mapping server-side.

The repository contained no authoritative place for explicit Retail confirmation or the future catalogue-onboarding preference. `partner_marketplace_settings` is Partner-wide, already consumed for marketplace presentation, and must not be used to imply readiness/live state. Product, inventory, grocery-store, and catalogue tables are runtime domains and are not appropriate onboarding preference owners.

Phase 4C therefore minimally extends `partner_retail_parent_categories` with nullable `catalogue_setup_mode` and `retail_confirmed_at`, plus deterministic `(partner_id,parent_category_id)` uniqueness. No new Retail ownership table is created. Legacy mappings remain incomplete until the Partner explicitly confirms and selects `SELF` or `ASSISTED`.

Storefront media is deferred: the only inspected canonical media field is Partner-wide `partner_marketplace_settings.logo_url`; no proven Retail-specific upload lifecycle exists. Store type, minimum order, preparation time, delivery radius/fees, operating state, products, price, stock, inventory, and marketplace activation remain deferred.

The migration is additive and inserts no Partner/category mappings. Application save may create only the authenticated Retail identity's deterministic missing mapping.
