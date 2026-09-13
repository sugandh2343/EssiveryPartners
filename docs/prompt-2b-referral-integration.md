# Prompt 2B referral integration

Prompt 2B reuses the Phase 23 Partner Referral tables and the shared Admin
`PartnerReferralRepository`. It introduces no database table or migration.

## Runtime dependencies

- The Partner API must be deployed at `/api/partner` beside the shared Admin
  `/api/helpers` directory.
- If the Admin API is elsewhere, set `ESSIVERY_ADMIN_API_PATH` to the directory
  containing its `helpers` folder.
- Phase 23A/23C referral migrations must already be present in production.
- The canonical `partners` and `delivery_partners` cores from Prompt 2A must be
  present.

## Deployment

1. Upload the complete `api/partner` directory.
2. Upload the shared compatibility changes to:
   - `api/helpers/partnerReferralClosureService.php`
   - `scripts/validate-partner-referrals-23c.mjs` (source/validation hosts only)
3. Build `frontend` with `npm run build` and deploy the generated `dist` to the
   Partner web root.
4. Run the Partner PHP tests and Admin Phase 23A/23C validators.
5. Run no SQL. Prompt 2B has no database changes.

The shared compatibility change only replaces the reconciliation join
`shopkeeper_profile.user_id` with canonical `partners.user_id`, excluding
soft-deleted Partner cores. It does not change claim, lifecycle, qualification,
reward, or Wallet rules.

