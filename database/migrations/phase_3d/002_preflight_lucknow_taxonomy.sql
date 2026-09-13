-- READ-ONLY preflight for the Lucknow location taxonomy repair.
SELECT expected.table_name,
       CASE WHEN actual.table_name IS NULL THEN 'MISSING - STOP' ELSE 'EXISTS' END AS table_state
FROM (
  SELECT 'states' AS table_name
  UNION ALL SELECT 'cities'
  UNION ALL SELECT 'pincodes'
  UNION ALL SELECT 'schema_migrations'
) expected
LEFT JOIN information_schema.tables actual
  ON actual.table_schema = 'u676721746_essivery'
 AND actual.table_name = expected.table_name
ORDER BY expected.table_name;

SELECT table_name,column_name,column_type,is_nullable,column_default
FROM information_schema.columns
WHERE table_schema = 'u676721746_essivery'
  AND (
    (table_name='states' AND column_name IN ('id','name','code','country_code','status')) OR
    (table_name='cities' AND column_name IN ('id','state_id','name','status')) OR
    (table_name='pincodes' AND column_name IN ('id','city_id','service_area_id','pincode','status'))
  )
ORDER BY table_name,ordinal_position;

SELECT id,name,code,country_code,status
FROM `u676721746_essivery`.`states`
WHERE country_code='IN' AND (code='UP' OR LOWER(name)='uttar pradesh');

SELECT c.id,c.name,c.status,c.state_id,s.name AS state_name,s.status AS state_status
FROM `u676721746_essivery`.`cities` c
JOIN `u676721746_essivery`.`states` s ON s.id=c.state_id
WHERE LOWER(c.name)='lucknow';

SELECT p.id,p.pincode,p.status,p.city_id,c.name AS city_name,s.name AS state_name
FROM `u676721746_essivery`.`pincodes` p
JOIN `u676721746_essivery`.`cities` c ON c.id=p.city_id
JOIN `u676721746_essivery`.`states` s ON s.id=c.state_id
WHERE p.pincode='226028';
