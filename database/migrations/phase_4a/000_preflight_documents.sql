-- Phase 4A phpMyAdmin-safe READ-ONLY preflight. Safe when tables are absent.
SELECT expected.table_name,CASE WHEN actual.table_name IS NULL THEN 'MISSING' ELSE 'EXISTS' END table_state,actual.engine,actual.table_collation
FROM (SELECT 'partner_documents' table_name UNION ALL SELECT 'delivery_partner_documents') expected
LEFT JOIN information_schema.tables actual ON actual.table_schema='u676721746_essivery' AND actual.table_name=expected.table_name ORDER BY expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name IN('partner_documents','delivery_partner_documents') ORDER BY table_name,ordinal_position;
SELECT table_name,index_name,non_unique,seq_in_index,column_name FROM information_schema.statistics
WHERE table_schema='u676721746_essivery' AND table_name IN('partner_documents','delivery_partner_documents') ORDER BY table_name,index_name,seq_in_index;

SELECT expected.table_name,CASE WHEN owners.table_name IS NULL THEN 'STOP: OWNER TABLE MISSING' ELSE 'PASS' END owner_table_check
FROM (SELECT 'partners' table_name UNION ALL SELECT 'delivery_partners') expected
LEFT JOIN information_schema.tables owners ON owners.table_schema='u676721746_essivery' AND owners.table_name=expected.table_name ORDER BY expected.table_name;

SET @q=IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='u676721746_essivery' AND table_name='partner_documents'),
'SELECT document_type,review_status,status,COUNT(*) row_count FROM `u676721746_essivery`.`partner_documents` GROUP BY document_type,review_status,status',
'SELECT ''partner_documents MISSING - migration will create it'' diagnostic');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @q=IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='u676721746_essivery' AND table_name='delivery_partner_documents'),
'SELECT document_type,review_status,status,COUNT(*) row_count FROM `u676721746_essivery`.`delivery_partner_documents` GROUP BY document_type,review_status,status',
'SELECT ''delivery_partner_documents MISSING - migration will create it'' diagnostic');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;

SET @q=IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='u676721746_essivery' AND table_name='partner_documents'),
'SELECT partner_id,document_type,COUNT(*) active_duplicates FROM `u676721746_essivery`.`partner_documents` WHERE status=''active'' GROUP BY partner_id,document_type HAVING COUNT(*)>1',
'SELECT ''PASS: partner_documents absent; no duplicates'' duplicate_check');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
SET @q=IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='u676721746_essivery' AND table_name='delivery_partner_documents'),
'SELECT delivery_partner_id,document_type,COUNT(*) active_duplicates FROM `u676721746_essivery`.`delivery_partner_documents` WHERE status=''active'' GROUP BY delivery_partner_id,document_type HAVING COUNT(*)>1',
'SELECT ''PASS: delivery_partner_documents absent; no duplicates'' duplicate_check');PREPARE s FROM @q;EXECUTE s;DEALLOCATE PREPARE s;
