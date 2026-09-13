-- Idempotent minimum canonical taxonomy required for the confirmed Lucknow address.
-- Run only after 002_preflight_lucknow_taxonomy.sql confirms the canonical columns.

INSERT INTO `u676721746_essivery`.`states` (`name`,`code`,`country_code`,`status`)
SELECT 'Uttar Pradesh','UP','IN','active'
WHERE NOT EXISTS (
  SELECT 1 FROM `u676721746_essivery`.`states`
  WHERE `country_code`='IN' AND (`code`='UP' OR LOWER(`name`)='uttar pradesh')
);

INSERT INTO `u676721746_essivery`.`cities` (`state_id`,`name`,`status`)
SELECT s.id,'Lucknow','active'
FROM `u676721746_essivery`.`states` s
WHERE s.country_code='IN' AND (s.code='UP' OR LOWER(s.name)='uttar pradesh')
  AND LOWER(s.status)='active'
  AND NOT EXISTS (
    SELECT 1 FROM `u676721746_essivery`.`cities` c
    WHERE c.state_id=s.id AND LOWER(c.name)='lucknow'
  );

INSERT INTO `u676721746_essivery`.`pincodes` (`city_id`,`service_area_id`,`pincode`,`status`)
SELECT c.id,NULL,'226028','active'
FROM `u676721746_essivery`.`cities` c
JOIN `u676721746_essivery`.`states` s ON s.id=c.state_id
WHERE s.country_code='IN' AND (s.code='UP' OR LOWER(s.name)='uttar pradesh')
  AND LOWER(s.status)='active' AND LOWER(c.name)='lucknow' AND LOWER(c.status)='active'
  AND NOT EXISTS (
    SELECT 1 FROM `u676721746_essivery`.`pincodes` p WHERE p.pincode='226028'
  );

INSERT IGNORE INTO `u676721746_essivery`.`schema_migrations` (`version`,`description`)
SELECT '2026_09_02_location_taxonomy_lucknow','Canonical Uttar Pradesh, Lucknow, and 226028 taxonomy required by Partner Location'
WHERE EXISTS (
  SELECT 1
  FROM `u676721746_essivery`.`states` s
  JOIN `u676721746_essivery`.`cities` c ON c.state_id=s.id
  JOIN `u676721746_essivery`.`pincodes` p ON p.city_id=c.id
  WHERE s.country_code='IN' AND (s.code='UP' OR LOWER(s.name)='uttar pradesh')
    AND LOWER(s.status)='active' AND LOWER(c.name)='lucknow' AND LOWER(c.status)='active'
    AND p.pincode='226028' AND LOWER(p.status)='active'
);
