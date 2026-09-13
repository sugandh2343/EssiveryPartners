SELECT DATABASE() selected_database,CASE WHEN DATABASE()='u676721746_essivery' THEN 'PASS' ELSE 'FAIL' END database_check;
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'FAIL' ELSE 'PASS' END table_check FROM (SELECT 'restaurants' table_name UNION ALL SELECT 'cuisines' UNION ALL SELECT 'restaurant_cuisines') expected LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name;
SELECT table_name,column_name,column_type FROM information_schema.columns WHERE table_schema='u676721746_essivery' AND table_name IN('restaurants','restaurant_cuisines') ORDER BY table_name,ordinal_position;
SELECT owner_user_id,COUNT(*) duplicate_count FROM `u676721746_essivery`.`restaurants` WHERE owner_user_id IS NOT NULL AND deleted_at IS NULL GROUP BY owner_user_id HAVING COUNT(*)>1;
SELECT restaurant_id,cuisine_id,COUNT(*) duplicate_count FROM `u676721746_essivery`.`restaurant_cuisines` GROUP BY restaurant_id,cuisine_id HAVING COUNT(*)>1;
SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END menu_mode_vocabulary FROM `u676721746_essivery`.`restaurants` WHERE menu_setup_mode IS NOT NULL AND menu_setup_mode NOT IN('SELF','ASSISTED');
SELECT CASE WHEN COUNT(*)>0 THEN 'PASS' ELSE 'FAIL - ACTIVE CUISINES REQUIRED' END active_cuisine_master FROM `u676721746_essivery`.`cuisines` WHERE status='active';
SELECT CASE WHEN COUNT(*)=1 THEN 'PASS' ELSE 'FAIL' END migration_marker FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4d_restaurant_setup';
SHOW CREATE TABLE `u676721746_essivery`.`restaurants`;SHOW CREATE TABLE `u676721746_essivery`.`restaurant_cuisines`;
