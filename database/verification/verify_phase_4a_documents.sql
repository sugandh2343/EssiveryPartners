-- Phase 4A Documents post-migration verification (read-only).

SELECT table_name,
       SUM(column_name='public_id') AS has_public_id,
       SUM(column_name='active_document_type') AS has_active_document_type
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('partner_documents','delivery_partner_documents')
GROUP BY table_name
ORDER BY table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('partner_documents','delivery_partner_documents')
ORDER BY table_name,ordinal_position;

SELECT table_name,index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery'
  AND table_name IN ('partner_documents','delivery_partner_documents')
GROUP BY table_name,index_name,non_unique
ORDER BY table_name,index_name;

SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS business_public_reference_check
FROM `u676721746_essivery`.`partner_documents`
WHERE public_id IS NULL OR CHAR_LENGTH(public_id)<>36;

SELECT CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS delivery_public_reference_check
FROM `u676721746_essivery`.`delivery_partner_documents`
WHERE public_id IS NULL OR CHAR_LENGTH(public_id)<>36;

SELECT partner_id,document_type,COUNT(*) AS active_duplicates
FROM `u676721746_essivery`.`partner_documents`
WHERE status='active'
GROUP BY partner_id,document_type
HAVING COUNT(*)>1;

SELECT delivery_partner_id,document_type,COUNT(*) AS active_duplicates
FROM `u676721746_essivery`.`delivery_partner_documents`
WHERE status='active'
GROUP BY delivery_partner_id,document_type
HAVING COUNT(*)>1;

SELECT version,description
FROM `u676721746_essivery`.`schema_migrations`
WHERE version='2026_08_31_4a_document_tables';

SHOW CREATE TABLE `u676721746_essivery`.`partner_documents`;
SHOW CREATE TABLE `u676721746_essivery`.`delivery_partner_documents`;
