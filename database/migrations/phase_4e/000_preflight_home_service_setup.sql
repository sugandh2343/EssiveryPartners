-- Phase 4E Home Service Setup: READ-ONLY production preflight.
-- Run against u676721746_essivery before 001_home_service_setup.sql.
SELECT expected.table_name,
       CASE WHEN actual.table_name IS NULL THEN 'MISSING - STOP' ELSE 'EXISTS' END AS table_state,
       actual.engine,
       actual.table_collation
FROM (
    SELECT 'home_service_categories' table_name UNION ALL
    SELECT 'home_service_subcategories' UNION ALL
    SELECT 'home_service_masters' UNION ALL
    SELECT 'home_service_provider_profiles' UNION ALL
    SELECT 'home_service_provider_services' UNION ALL
    SELECT 'partners' UNION ALL
    SELECT 'partner_business_modules' UNION ALL
    SELECT 'partner_onboarding_applications' UNION ALL
    SELECT 'partner_onboarding_steps' UNION ALL
    SELECT 'schema_migrations'
) expected
LEFT JOIN information_schema.tables actual
  ON actual.table_schema = 'u676721746_essivery' AND actual.table_name = expected.table_name
ORDER BY expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('home_service_categories','home_service_subcategories','home_service_masters','home_service_provider_profiles','home_service_provider_services')
ORDER BY table_name,ordinal_position;

SELECT partner_id,COUNT(*) duplicate_count
FROM `u676721746_essivery`.`home_service_provider_profiles`
GROUP BY partner_id
HAVING COUNT(*)>1;
