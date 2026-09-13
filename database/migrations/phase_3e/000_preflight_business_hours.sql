-- Phase 3E Business Hours: READ-ONLY production preflight.
-- Target: u676721746_essivery. Does not modify schema or data.

SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours';

SELECT column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours'
ORDER BY ordinal_position;

SELECT index_name,non_unique,seq_in_index,column_name
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery' AND table_name='partner_business_hours'
ORDER BY index_name,seq_in_index;

SELECT constraint_name,constraint_type
FROM information_schema.table_constraints
WHERE constraint_schema='u676721746_essivery' AND table_name='partner_business_hours'
ORDER BY constraint_type,constraint_name;

SELECT COUNT(*) AS existing_row_count,
       COUNT(DISTINCT partner_id) AS partners_with_hours,
       SUM(CASE WHEN is_open=1 THEN 1 ELSE 0 END) AS open_rows,
       SUM(CASE WHEN is_open=1 AND closes_at<=opens_at THEN 1 ELSE 0 END) AS legacy_overnight_or_ambiguous_rows
FROM `u676721746_essivery`.`partner_business_hours`;

SELECT partner_id,day_of_week,COUNT(*) AS rows_per_day
FROM `u676721746_essivery`.`partner_business_hours`
GROUP BY partner_id,day_of_week
HAVING COUNT(*)>1;

SHOW CREATE TABLE `u676721746_essivery`.`partner_business_hours`;

