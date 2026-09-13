SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns WHERE table_schema='u676721746_essivery' AND table_name IN('partner_onboarding_applications','partner_onboarding_status_history') ORDER BY table_name,ordinal_position;

SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list
FROM information_schema.statistics WHERE table_schema='u676721746_essivery' AND table_name='partner_onboarding_status_history' GROUP BY index_name,non_unique ORDER BY index_name;

SELECT application_status,COUNT(*) row_count FROM `u676721746_essivery`.`partner_onboarding_applications` WHERE is_current=1 AND status='active' GROUP BY application_status;
SELECT version,description FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4g_review_submit';

