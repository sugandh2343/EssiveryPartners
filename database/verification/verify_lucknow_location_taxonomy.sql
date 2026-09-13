SELECT s.id AS state_id,s.name AS state_name,s.code,s.country_code,s.status AS state_status,
       c.id AS city_id,c.name AS city_name,c.status AS city_status,
       p.id AS pincode_id,p.pincode,p.status AS pincode_status
FROM `u676721746_essivery`.`states` s
JOIN `u676721746_essivery`.`cities` c ON c.state_id=s.id
JOIN `u676721746_essivery`.`pincodes` p ON p.city_id=c.id
WHERE s.country_code='IN'
  AND (s.code='UP' OR LOWER(s.name)='uttar pradesh')
  AND LOWER(c.name)='lucknow'
  AND p.pincode='226028';

SELECT version,description
FROM `u676721746_essivery`.`schema_migrations`
WHERE version='2026_09_02_location_taxonomy_lucknow';
