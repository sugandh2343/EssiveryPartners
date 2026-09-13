-- Phase 4B Bank/Payout: phpMyAdmin-safe READ-ONLY production preflight.
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'MISSING' ELSE 'EXISTS - STOP AND INSPECT' END table_state,actual.engine,actual.table_collation
FROM (
 SELECT 'partner_bank_accounts' table_name UNION ALL
 SELECT 'delivery_partner_bank_accounts' UNION ALL
 SELECT 'partner_payout_accounts' UNION ALL
 SELECT 'bank_accounts'
) expected LEFT JOIN information_schema.tables actual
 ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name
ORDER BY expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns WHERE table_schema='u676721746_essivery'
 AND (table_name LIKE '%bank%' OR table_name LIKE '%payout%') ORDER BY table_name,ordinal_position;

SELECT expected.table_name,CASE WHEN owners.table_name IS NULL THEN 'STOP: OWNER TABLE MISSING' ELSE 'PASS' END owner_table_check
FROM (SELECT 'partners' table_name UNION ALL SELECT 'delivery_partners') expected
LEFT JOIN information_schema.tables owners ON owners.table_schema='u676721746_essivery' AND owners.table_name=expected.table_name
ORDER BY expected.table_name;

SELECT table_name,column_name,column_type
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
 AND (column_name LIKE '%account_number%' OR column_name LIKE '%ifsc%' OR column_name LIKE '%upi%')
ORDER BY table_name,column_name;

SELECT table_name FROM information_schema.tables
WHERE table_schema='u676721746_essivery'
 AND table_name IN('wallets','wallet_ledger','partner_statements','settlement_statements','payouts','withdrawals')
ORDER BY table_name;
