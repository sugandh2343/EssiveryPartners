-- Phase 3D Location columns: READ-ONLY post-migration verification.

USE `u676721746_essivery`;

SELECT DATABASE() AS selected_database,
       CASE WHEN DATABASE() = 'u676721746_essivery' THEN 'PASS' ELSE 'FAIL' END AS database_check;

SELECT CASE WHEN COUNT(*) = 3 THEN 'PASS' ELSE 'FAIL' END AS required_columns_check
FROM information_schema.columns
WHERE table_schema = 'u676721746_essivery'
  AND (
    (table_name = 'partners' AND column_name = 'locality' AND column_type = 'varchar(150)' AND is_nullable = 'YES')
    OR
    (table_name = 'delivery_partners' AND column_name = 'address_line_2' AND column_type = 'varchar(255)' AND is_nullable = 'YES')
    OR
    (table_name = 'delivery_partners' AND column_name = 'locality' AND column_type = 'varchar(150)' AND is_nullable = 'YES')
  );

SELECT table_name, column_name, column_type, is_nullable, column_default, ordinal_position
FROM information_schema.columns
WHERE table_schema = 'u676721746_essivery'
  AND (
    (table_name = 'partners' AND column_name IN ('address_line_1','address_line_2','locality','landmark','city_id','state_id','pincode','latitude','longitude'))
    OR
    (table_name = 'delivery_partners' AND column_name IN ('address_line','address_line_2','locality','landmark','city_id','state_id','pincode','latitude','longitude'))
  )
ORDER BY table_name, ordinal_position;

SELECT 'partners' AS table_name, COUNT(*) AS row_count FROM `u676721746_essivery`.`partners`
UNION ALL
SELECT 'delivery_partners', COUNT(*) FROM `u676721746_essivery`.`delivery_partners`;

SELECT version, description
FROM `u676721746_essivery`.`schema_migrations`
WHERE version = '2026_08_30_3d_location_columns';

SHOW CREATE TABLE `u676721746_essivery`.`partners`;
SHOW CREATE TABLE `u676721746_essivery`.`delivery_partners`;
