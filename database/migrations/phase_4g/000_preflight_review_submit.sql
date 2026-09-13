-- Phase 4G Review/Submit: READ-ONLY production preflight.
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'MISSING - STOP' ELSE 'EXISTS' END table_state,actual.engine,actual.table_collation
FROM (SELECT 'partner_onboarding_applications' table_name UNION ALL SELECT 'partner_onboarding_steps' UNION ALL SELECT 'partners' UNION ALL SELECT 'delivery_partners' UNION ALL SELECT 'audit_logs' UNION ALL SELECT 'schema_migrations') expected
LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name ORDER BY expected.table_name;

SELECT column_name,column_type,is_nullable,column_default,extra
FROM information_schema.columns WHERE table_schema='u676721746_essivery' AND table_name='partner_onboarding_applications' ORDER BY ordinal_position;

SELECT index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list
FROM information_schema.statistics WHERE table_schema='u676721746_essivery' AND table_name='partner_onboarding_applications'
GROUP BY index_name,non_unique ORDER BY index_name;

SELECT public_id,business_partner_id,delivery_partner_id,application_status,completion_percentage
FROM `u676721746_essivery`.`partner_onboarding_applications`
WHERE (business_partner_id IS NULL)=(delivery_partner_id IS NULL) OR is_current<>1 OR status<>'active';

SELECT application_id,SUM(is_required=1 AND status='active') required_count,SUM(is_required=1 AND status='active' AND step_status='complete') complete_count
FROM `u676721746_essivery`.`partner_onboarding_steps` GROUP BY application_id
HAVING required_count<>complete_count AND application_id IN(SELECT id FROM `u676721746_essivery`.`partner_onboarding_applications` WHERE completion_percentage=100);

