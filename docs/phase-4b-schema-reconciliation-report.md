# Essivery Partner Phase 4B — Bank & Payout schema reconciliation

Date: 2026-08-31  
Decision: **schema and encryption gate triggered; application implementation stopped**

## Inspection result

The authoritative schema, Admin backend/UI, Delivery administration, wallets, statements, settlements, Partner code, and shared User encryption utilities were inspected. No canonical Business or Delivery Bank/Payout table or production Bank repository exists. Admin displays Bank values only from mock data. Wallet and settlement tables do not own payout credentials and must remain separate.

The project does have an established sensitive-value convention: AES-256-GCM using a server environment secret, random 12-byte IV, and 16-byte authentication tag. No existing Bank encryption secret exists. Storing a full account number in `partners`, `delivery_partners`, or plaintext would fail the Phase 4B security gate.

## Recommended canonical ownership

- Business: `partner_bank_accounts.partner_id → partners.id`
- Delivery: `delivery_partner_bank_accounts.delivery_partner_id → delivery_partners.id`

Each table permits inactive history and enforces exactly one active/current row per owner through a generated active key. It stores an opaque public ID, account-holder name, AES-GCM ciphertext, last four digits, encryption version, IFSC, review state, and timestamps. It stores neither confirmation input nor wallet/settlement/payout transactions.

Separate tables preserve the existing Business/Delivery ownership boundary and make future Admin review explicit. A single nullable dual-owner table was rejected because it weakens foreign-key ownership and increases cross-identity risk.

## Encryption decision

The future application must require `PARTNER_BANK_ENCRYPTION_KEY` with at least 32 characters and fail closed when absent. It will derive a 256-bit key using SHA-256 and store a versioned AES-256-GCM envelope (random IV + authentication tag + ciphertext) in `account_number_encrypted`. Only `account_number_last4` may be projected. Key rotation is deferred but `encryption_version` reserves an explicit migration path. OpenSSL is required.

The account fingerprint is deferred because Phase 4B needs no cross-owner deduplication. UPI, Bank Name, and Account Type are deferred because no canonical consumer/storage exists. Bank Account + IFSC is the sole Phase 4B payout destination.

## Compatibility and boundaries

The migration is additive and creates no account records. It does not alter Partner, Delivery, wallet, ledger, payout, statement, settlement, approval, marketplace, serviceability, document, or authentication data. Bank save application code must later perform transactional retire/insert, reset review to pending, update setup progress, and audit only last4/IFSC/review state.

## Package order

1. Run `database/migrations/phase_4b/000_preflight_bank_accounts.sql`.
2. Confirm both owner tables pass and no unexpected canonical Bank table exists.
3. Back up the database.
4. Run `database/migrations/phase_4b/001_secure_bank_accounts.sql` once.
5. Run `database/verification/verify_phase_4b_bank_accounts.sql` and retain the output.
6. Configure `PARTNER_BANK_ENCRYPTION_KEY` before deploying any future Phase 4B PHP.

No Phase 4B controller, repository, service, route, frontend page, or test fixture was implemented because the prompt requires operator-approved schema and encryption reconciliation first.
