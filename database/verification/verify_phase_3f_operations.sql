-- Phase 3F Operations/Fulfilment post-migration verification (read-only).

SELECT CASE WHEN COUNT(*)=9 THEN 'PASS' ELSE 'FAIL' END AS required_columns_check
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name='partner_fulfilment_settings'
  AND column_name IN (
    'public_id','partner_id','module_code','essivery_delivery','self_delivery',
    'customer_pickup','service_at_customer_location','status','updated_at'
  );

SELECT column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name='partner_fulfilment_settings'
ORDER BY ordinal_position;

SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery'
  AND table_name='partner_fulfilment_settings'
GROUP BY index_name,non_unique
ORDER BY index_name;

SELECT constraint_name,constraint_type
FROM information_schema.table_constraints
WHERE constraint_schema='u676721746_essivery'
  AND table_name='partner_fulfilment_settings'
ORDER BY constraint_type,constraint_name;

SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS invalid_module_or_mode_check
FROM `u676721746_essivery`.`partner_fulfilment_settings`
WHERE module_code NOT IN ('RETAIL','RESTAURANT','HOME_SERVICE')
   OR (module_code IN ('RETAIL','RESTAURANT')
       AND essivery_delivery=0 AND self_delivery=0 AND customer_pickup=0)
   OR (module_code='HOME_SERVICE' AND service_at_customer_location=0)
   OR (module_code='HOME_SERVICE'
       AND (essivery_delivery<>0 OR self_delivery<>0 OR customer_pickup<>0));

SELECT partner_id,module_code,COUNT(*) AS duplicate_count
FROM `u676721746_essivery`.`partner_fulfilment_settings`
GROUP BY partner_id,module_code
HAVING COUNT(*)>1;

SELECT COUNT(*) AS fulfilment_rows
FROM `u676721746_essivery`.`partner_fulfilment_settings`;

SELECT version,description
FROM `u676721746_essivery`.`schema_migrations`
WHERE version='2026_08_30_3f_partner_fulfilment';

SHOW CREATE TABLE `u676721746_essivery`.`partner_fulfilment_settings`;
