# Essivery Partner API foundation

Prompt 2A provides PartnerContext/bootstrap. Prompt 2B adds protected referral
context, claim, and continue-without-referral routes while reusing the shared
Phase 23 referral engine.

Deploy this directory as `public_html/api/partner`. It expects the existing User API at the sibling path `public_html/api/user`. If the User API is elsewhere, set `ESSIVERY_USER_API_PATH` to its absolute server path.

The Partner API loads the existing User API bootstrap and therefore uses the same database, JWT secrets, issuer/audience, sessions, identities, response/error core, logger, and idempotency table. PHP 8.1+ is required by the shared User API.

Production requirements:

- `partners` exists with a unique `user_id`.
- `delivery_partners` exists with a unique `user_id`.
- The existing User API environment contains the production database and JWT configuration.
- `CORS_ALLOWED_ORIGINS` may include other Essivery origins; canonical `https://partners.essivery.in` is explicitly added by the Partner entry point.
- Apache rewrite and Authorization forwarding are enabled.

Bootstrap fails closed with `CONFIGURATION_ERROR` if the canonical ownership columns or unique indexes are missing. For a deployment where either canonical core is absent, run the preflight, additive migration, and verification package documented in `../../docs/prompt-2a-schema-reconciliation.md`. The package creates no Partner or module rows and does not alter User authentication.
