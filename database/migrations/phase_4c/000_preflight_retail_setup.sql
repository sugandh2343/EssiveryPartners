-- Phase 4C READ-ONLY production preflight. Review every result before migration.
SELECT DATABASE() selected_database,CASE WHEN DATABASE()='u676721746_essivery' THEN 'PASS' ELSE 'FAIL - WRONG DATABASE' END database_check;
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'MISSING - STOP' ELSE 'EXISTS' END table_state
FROM (SELECT 'partners' table_name UNION ALL SELECT 'users' UNION ALL SELECT 'parent_categories' UNION ALL SELECT 'partner_business_modules' UNION ALL SELECT 'partner_retail_parent_categories' UNION ALL SELECT 'partner_onboarding_applications' UNION ALL SELECT 'partner_onboarding_steps' UNION ALL SELECT 'schema_migrations') expected
LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name ORDER BY expected.table_name;
SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position FROM information_schema.columns WHERE table_schema='u676721746_essivery' AND table_name IN('users','partners','parent_categories','partner_business_modules','partner_retail_parent_categories') ORDER BY table_name,ordinal_position;
SELECT table_name,index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list FROM information_schema.statistics WHERE table_schema='u676721746_essivery' AND table_name IN('partner_business_modules','partner_retail_parent_categories') GROUP BY table_name,index_name,non_unique ORDER BY table_name,index_name;
SELECT partner_id,parent_category_id,COUNT(*) duplicate_count FROM `u676721746_essivery`.`partner_retail_parent_categories` GROUP BY partner_id,parent_category_id HAVING COUNT(*)>1;
SELECT partner_id,module_code,COUNT(*) duplicate_count FROM `u676721746_essivery`.`partner_business_modules` WHERE status='active' GROUP BY partner_id,module_code HAVING COUNT(*)>1;
SELECT COUNT(*) existing_retail_mapping_rows FROM `u676721746_essivery`.`partner_retail_parent_categories`;
SHOW CREATE TABLE `u676721746_essivery`.`partner_retail_parent_categories`;
SHOW CREATE TABLE `u676721746_essivery`.`partner_business_modules`;
