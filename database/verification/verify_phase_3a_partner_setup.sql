-- Read-only verification for Prompt 3A.
SELECT CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'FAIL' END AS setup_tables
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('partner_onboarding_applications','partner_onboarding_steps');

SELECT CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'FAIL' END AS owner_foreign_keys
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'partner_onboarding_applications'
  AND referenced_table_name IN ('partners','delivery_partners');

SELECT CASE WHEN COUNT(*) = 2 THEN 'PASS' ELSE 'FAIL' END AS current_owner_unique_indexes
FROM (
  SELECT index_name
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'partner_onboarding_applications'
    AND index_name IN ('uq_partner_setup_business_current','uq_partner_setup_delivery_current')
  GROUP BY index_name
) indexes_found;

SELECT CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'FAIL' END AS application_step_unique_index
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'partner_onboarding_steps'
  AND index_name = 'uq_partner_setup_step'
  AND non_unique = 0
GROUP BY index_name;

SELECT public_id, business_partner_id, delivery_partner_id
FROM partner_onboarding_applications
WHERE (business_partner_id IS NULL) = (delivery_partner_id IS NULL);

SELECT application_id, step_code, COUNT(*) AS duplicate_count
FROM partner_onboarding_steps
GROUP BY application_id, step_code
HAVING COUNT(*) > 1;

SELECT public_id, completion_percentage
FROM partner_onboarding_applications
WHERE completion_percentage NOT BETWEEN 0 AND 100;
