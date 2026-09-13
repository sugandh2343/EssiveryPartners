-- Run after 001_reconcile_partner_core.sql. All summarized checks must PASS.

SELECT
  expected.table_name,
  IF(actual.table_name IS NOT NULL, 'PASS', 'FAIL') AS table_exists
FROM (
  SELECT 'partners' AS table_name
  UNION ALL SELECT 'delivery_partners'
) expected
LEFT JOIN information_schema.tables actual
  ON actual.table_schema = DATABASE()
 AND actual.table_name = expected.table_name
ORDER BY expected.table_name;

SELECT
  expected.table_name,
  COUNT(actual.column_name) AS required_columns_found,
  expected.required_count,
  IF(COUNT(actual.column_name) = expected.required_count, 'PASS', 'FAIL') AS required_columns
FROM (
  SELECT 'partners' AS table_name, 9 AS required_count
  UNION ALL SELECT 'delivery_partners', 8
) expected
LEFT JOIN information_schema.columns actual
  ON actual.table_schema = DATABASE()
 AND actual.table_name = expected.table_name
 AND actual.column_name IN (
   'id', 'public_id', 'user_id', 'business_name', 'owner_name', 'name',
   'mobile', 'onboarding_status', 'approval_status', 'status'
 )
GROUP BY expected.table_name, expected.required_count
ORDER BY expected.table_name;

SELECT
  expected.table_name,
  IF(COUNT(indexes_found.index_name) = 1, 'PASS', 'FAIL') AS unique_user_ownership
FROM (
  SELECT 'partners' AS table_name
  UNION ALL SELECT 'delivery_partners'
) expected
LEFT JOIN (
  SELECT table_name, index_name
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
  GROUP BY table_name, index_name
  HAVING MIN(non_unique) = 0
     AND COUNT(*) = 1
     AND GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'user_id'
) indexes_found ON indexes_found.table_name = expected.table_name
GROUP BY expected.table_name
ORDER BY expected.table_name;

SELECT
  'partners defaults' AS check_name,
  IF(
    SUM(column_name = 'onboarding_status' AND column_default = 'draft') = 1
    AND SUM(column_name = 'approval_status' AND column_default = 'pending') = 1
    AND SUM(column_name = 'status' AND column_default = 'active') = 1,
    'PASS', 'FAIL'
  ) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'partners';

SELECT
  'delivery_partners defaults' AS check_name,
  IF(
    SUM(column_name = 'onboarding_status' AND column_default = 'draft') = 1
    AND SUM(column_name = 'approval_status' AND column_default = 'pending') = 1
    AND SUM(column_name = 'availability_status' AND column_default = 'offline') = 1
    AND SUM(column_name = 'status' AND column_default = 'active') = 1,
    'PASS', 'FAIL'
  ) AS result
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'delivery_partners';

SELECT 'partners' AS table_name, COUNT(*) AS row_count FROM partners
UNION ALL
SELECT 'delivery_partners', COUNT(*) FROM delivery_partners;
