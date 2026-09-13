SELECT DATABASE() selected_database,CASE WHEN DATABASE()='u676721746_essivery' THEN 'PASS' ELSE 'FAIL' END database_check;
SELECT expected.column_name,CASE WHEN actual.column_name IS NULL THEN 'FAIL - MISSING' ELSE 'PASS' END column_check,actual.column_type
FROM (SELECT 'partner_id' column_name UNION ALL SELECT 'parent_category_id' UNION ALL SELECT 'catalogue_setup_mode' UNION ALL SELECT 'retail_confirmed_at' UNION ALL SELECT 'status' UNION ALL SELECT 'created_at' UNION ALL SELECT 'updated_at') expected
LEFT JOIN information_schema.columns actual ON actual.table_schema='u676721746_essivery' AND actual.table_name='partner_retail_parent_categories' AND actual.column_name=expected.column_name;
SELECT CASE WHEN COUNT(*)=1 THEN 'PASS' ELSE 'FAIL' END unique_partner_category_index FROM (SELECT index_name FROM information_schema.statistics WHERE table_schema='u676721746_essivery' AND table_name='partner_retail_parent_categories' AND non_unique=0 GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index)='partner_id,parent_category_id') x;
SELECT partner_id,parent_category_id,COUNT(*) duplicate_count FROM `u676721746_essivery`.`partner_retail_parent_categories` GROUP BY partner_id,parent_category_id HAVING COUNT(*)>1;
SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END catalogue_mode_vocabulary FROM `u676721746_essivery`.`partner_retail_parent_categories` WHERE catalogue_setup_mode IS NOT NULL AND catalogue_setup_mode NOT IN('SELF','ASSISTED');
SELECT CASE WHEN COUNT(*)=1 THEN 'PASS' ELSE 'FAIL' END migration_marker FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_08_31_4c_retail_setup';
SHOW CREATE TABLE `u676721746_essivery`.`partner_retail_parent_categories`;
