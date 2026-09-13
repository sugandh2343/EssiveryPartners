-- Phase 3D Location columns: READ-ONLY production preflight.
-- phpMyAdmin-safe: every schema/table reference is fully qualified.

SELECT 'partners' AS table_name,
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'u676721746_essivery' AND table_name = 'partners')
            THEN 'EXISTS' ELSE 'MISSING - STOP' END AS table_state
UNION ALL
SELECT 'delivery_partners',
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'u676721746_essivery' AND table_name = 'delivery_partners')
            THEN 'EXISTS' ELSE 'MISSING - STOP' END;

SELECT 'partners.locality' AS required_column,
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'u676721746_essivery' AND table_name = 'partners' AND column_name = 'locality')
            THEN 'EXISTS - MIGRATION WILL SKIP' ELSE 'MISSING - WILL BE ADDED' END AS migration_action
UNION ALL
SELECT 'delivery_partners.address_line_2',
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'u676721746_essivery' AND table_name = 'delivery_partners' AND column_name = 'address_line_2')
            THEN 'EXISTS - MIGRATION WILL SKIP' ELSE 'MISSING - WILL BE ADDED' END
UNION ALL
SELECT 'delivery_partners.locality',
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'u676721746_essivery' AND table_name = 'delivery_partners' AND column_name = 'locality')
            THEN 'EXISTS - MIGRATION WILL SKIP' ELSE 'MISSING - WILL BE ADDED' END;

SELECT table_name, column_name, column_type, is_nullable, ordinal_position
FROM information_schema.columns
WHERE table_schema = 'u676721746_essivery'
  AND table_name IN ('partners','delivery_partners')
  AND column_name IN ('address_line','address_line_1','address_line_2','locality','landmark','city_id','state_id','pincode','latitude','longitude')
ORDER BY table_name, ordinal_position;

SELECT 'partners' AS table_name, COUNT(*) AS row_count FROM `u676721746_essivery`.`partners`
UNION ALL
SELECT 'delivery_partners', COUNT(*) FROM `u676721746_essivery`.`delivery_partners`;

SELECT 'schema_migrations' AS table_name,
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'u676721746_essivery' AND table_name = 'schema_migrations')
            THEN 'EXISTS' ELSE 'MISSING - STOP' END AS registry_state;

