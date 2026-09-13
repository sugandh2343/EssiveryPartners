-- Phase 4B post-migration verification (read-only).
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'FAIL' ELSE 'PASS' END table_check
FROM (SELECT 'partner_bank_accounts' table_name UNION ALL SELECT 'delivery_partner_bank_accounts') expected
LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name IN('partner_bank_accounts','delivery_partner_bank_accounts') ORDER BY table_name,ordinal_position;
SELECT table_name,index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) indexed_columns FROM information_schema.statistics
WHERE table_schema='u676721746_essivery' AND table_name IN('partner_bank_accounts','delivery_partner_bank_accounts') GROUP BY table_name,index_name,non_unique ORDER BY table_name,index_name;

SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END business_plaintext_shape_check FROM `u676721746_essivery`.`partner_bank_accounts`
WHERE account_number_encrypted REGEXP '^[0-9]{6,20}$' OR account_number_last4 NOT REGEXP '^[0-9]{4}$' OR ifsc_code NOT REGEXP '^[A-Z]{4}0[A-Z0-9]{6}$';
SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END delivery_plaintext_shape_check FROM `u676721746_essivery`.`delivery_partner_bank_accounts`
WHERE account_number_encrypted REGEXP '^[0-9]{6,20}$' OR account_number_last4 NOT REGEXP '^[0-9]{4}$' OR ifsc_code NOT REGEXP '^[A-Z]{4}0[A-Z0-9]{6}$';

SELECT partner_id,COUNT(*) active_count FROM `u676721746_essivery`.`partner_bank_accounts` WHERE status='active' GROUP BY partner_id HAVING COUNT(*)>1;
SELECT delivery_partner_id,COUNT(*) active_count FROM `u676721746_essivery`.`delivery_partner_bank_accounts` WHERE status='active' GROUP BY delivery_partner_id HAVING COUNT(*)>1;
SELECT version,description FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_08_31_4b_secure_bank_accounts';
SHOW CREATE TABLE `u676721746_essivery`.`partner_bank_accounts`;
SHOW CREATE TABLE `u676721746_essivery`.`delivery_partner_bank_accounts`;
