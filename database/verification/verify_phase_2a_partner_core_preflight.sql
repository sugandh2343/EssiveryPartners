-- Run before the migration. Every row must report PASS before continuing.

SELECT
  'users table' AS check_name,
  IF(COUNT(*) = 1, 'PASS', 'FAIL') AS result
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'users';

SELECT
  'users.id BIGINT UNSIGNED' AS check_name,
  IF(COUNT(*) = 1, 'PASS', 'FAIL') AS result
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'users'
  AND column_name = 'id'
  AND data_type = 'bigint'
  AND column_type LIKE '%unsigned%';

SELECT
  'no legacy business Partner table' AS check_name,
  IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result,
  GROUP_CONCAT(table_name ORDER BY table_name) AS conflicting_tables
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('shopkeeper_profile', 'partner_profile', 'partner');

SELECT
  table_name,
  CASE WHEN table_name IN ('partners', 'delivery_partners') THEN 'EXISTS' ELSE 'UNKNOWN' END AS current_state
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'partners', 'delivery_partners', 'partner_business_modules',
    'partner_retail_parent_categories', 'partner_marketplace_settings',
    'partner_service_areas', 'partner_business_hours', 'partner_documents',
    'partner_reviews', 'partner_status_history'
  )
ORDER BY table_name;

