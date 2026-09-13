-- Phase 4E post-migration verification.
SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('home_service_provider_profiles','home_service_provider_setup_services')
ORDER BY table_name,ordinal_position;

SELECT table_name,index_name,non_unique,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery'
  AND table_name='home_service_provider_setup_services'
GROUP BY table_name,index_name,non_unique
ORDER BY index_name;

SELECT COUNT(*) AS active_service_options
FROM `u676721746_essivery`.`home_service_masters` m
JOIN `u676721746_essivery`.`home_service_categories` c ON c.id=m.category_id AND c.status='active'
LEFT JOIN `u676721746_essivery`.`home_service_subcategories` s ON s.id=m.subcategory_id
WHERE m.status='active' AND (s.id IS NULL OR s.status='active');

SELECT version,description
FROM `u676721746_essivery`.`schema_migrations`
WHERE version='2026_09_01_4e_home_service_setup';
