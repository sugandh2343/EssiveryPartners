-- Phase 3F Operations & Fulfilment: READ-ONLY production preflight.
-- Target: u676721746_essivery. This file does not modify schema or data.

SELECT table_name,engine,table_collation
FROM information_schema.tables
WHERE table_schema='u676721746_essivery'
  AND table_name IN (
    'partners','partner_business_modules','partner_marketplace_settings',
    'partner_fulfilment_settings','grocery_stores','restaurants',
    'home_service_provider_profiles','delivery_partners'
  )
ORDER BY table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name IN (
    'partners','partner_business_modules','partner_marketplace_settings',
    'partner_fulfilment_settings','grocery_stores','restaurants',
    'home_service_provider_profiles','delivery_partners'
  )
ORDER BY table_name,ordinal_position;

SELECT table_name,index_name,non_unique,seq_in_index,column_name
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('partner_business_modules','partner_marketplace_settings','partner_fulfilment_settings')
ORDER BY table_name,index_name,seq_in_index;

SELECT module_code,status,COUNT(*) AS module_rows
FROM `u676721746_essivery`.`partner_business_modules`
GROUP BY module_code,status
ORDER BY module_code,status;

SELECT COUNT(*) AS marketplace_rows,
       COUNT(DISTINCT partner_id) AS marketplace_partners
FROM `u676721746_essivery`.`partner_marketplace_settings`;

SELECT CASE
         WHEN COUNT(*)=1 THEN 'PASS'
         ELSE 'STOP: partners.id must be BIGINT UNSIGNED before migration'
       END AS partner_key_check
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name='partners'
  AND column_name='id'
  AND column_type='bigint(20) unsigned';

SELECT CASE
         WHEN COUNT(*)=0 THEN 'PASS: candidate table is not yet present'
         ELSE 'STOP: candidate table already exists; inspect before migration'
       END AS candidate_table_check
FROM information_schema.tables
WHERE table_schema='u676721746_essivery'
  AND table_name='partner_fulfilment_settings';

SHOW CREATE TABLE `u676721746_essivery`.`partner_marketplace_settings`;
SHOW CREATE TABLE `u676721746_essivery`.`partner_business_modules`;
SHOW CREATE TABLE `u676721746_essivery`.`partners`;
