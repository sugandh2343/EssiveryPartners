-- Phase 4F Delivery Setup: READ-ONLY production preflight.
SELECT expected.table_name,
       CASE WHEN actual.table_name IS NOT NULL THEN 'EXISTS'
            WHEN expected.table_name='delivery_partner_vehicles' THEN 'MISSING - 001 WILL CREATE'
            ELSE 'MISSING - STOP' END table_state,
       actual.engine,actual.table_collation
FROM (SELECT 'delivery_partners' table_name UNION ALL SELECT 'delivery_partner_vehicles' UNION ALL SELECT 'delivery_partner_documents' UNION ALL SELECT 'partner_onboarding_applications' UNION ALL SELECT 'partner_onboarding_steps' UNION ALL SELECT 'schema_migrations') expected
LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name
ORDER BY expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name IN('delivery_partners','delivery_partner_vehicles','delivery_partner_documents')
ORDER BY table_name,ordinal_position;

SET @phase4f_vehicle_table_exists := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema='u676721746_essivery' AND table_name='delivery_partner_vehicles'
);
SET @phase4f_duplicate_sql := IF(
  @phase4f_vehicle_table_exists=1,
  'SELECT delivery_partner_id,COUNT(*) active_primary_count FROM `u676721746_essivery`.`delivery_partner_vehicles` WHERE is_primary=1 AND status=''active'' GROUP BY delivery_partner_id HAVING COUNT(*)>1',
  'SELECT ''SKIPPED - delivery_partner_vehicles does not exist; 001 will create it'' AS duplicate_check'
);
PREPARE phase4f_duplicate_check FROM @phase4f_duplicate_sql;
EXECUTE phase4f_duplicate_check;
DEALLOCATE PREPARE phase4f_duplicate_check;

SET @phase4f_legacy_sql := IF(
  @phase4f_vehicle_table_exists=1,
  'SELECT LOWER(vehicle_type) legacy_vehicle_type,COUNT(*) row_count FROM `u676721746_essivery`.`delivery_partner_vehicles` WHERE status=''active'' GROUP BY LOWER(vehicle_type) ORDER BY legacy_vehicle_type',
  'SELECT ''SKIPPED - no legacy vehicle rows'' AS legacy_vehicle_check'
);
PREPARE phase4f_legacy_check FROM @phase4f_legacy_sql;
EXECUTE phase4f_legacy_check;
DEALLOCATE PREPARE phase4f_legacy_check;
