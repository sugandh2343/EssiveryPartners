-- Phase 3E Business Hours post-migration verification (read-only).

SELECT CASE WHEN COUNT(*)=6 THEN 'PASS' ELSE 'FAIL' END AS required_columns_check
FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours'
  AND column_name IN ('module_code','slot_order','is_closed','is_24_hours','is_overnight','status');

SELECT column_name,column_type,is_nullable,column_default,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours'
ORDER BY ordinal_position;

SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours'
GROUP BY index_name,non_unique
ORDER BY index_name;

SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS semantic_consistency_check
FROM `u676721746_essivery`.`partner_business_hours`
WHERE (is_closed=1 AND (is_open<>0 OR opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_24_hours=1 AND (is_open<>1 OR is_closed<>0 OR opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_overnight=1 AND (is_open<>1 OR is_closed<>0 OR is_24_hours<>0 OR closes_at>=opens_at));

SELECT partner_id,module_code,day_of_week,slot_order,COUNT(*) AS duplicate_count
FROM `u676721746_essivery`.`partner_business_hours`
GROUP BY partner_id,module_code,day_of_week,slot_order
HAVING COUNT(*)>1;

SELECT version,description FROM `u676721746_essivery`.`schema_migrations`
WHERE version='2026_08_30_3e_business_hours';

SHOW CREATE TABLE `u676721746_essivery`.`partner_business_hours`;

