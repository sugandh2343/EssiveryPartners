SELECT table_name,column_name,column_type,is_nullable,column_default,extra,ordinal_position
FROM information_schema.columns
WHERE table_schema='u676721746_essivery' AND table_name IN('delivery_vehicle_types','delivery_partner_vehicles')
ORDER BY table_name,ordinal_position;

SELECT table_name,index_name,non_unique,GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list
FROM information_schema.statistics
WHERE table_schema='u676721746_essivery' AND table_name IN('delivery_vehicle_types','delivery_partner_vehicles')
GROUP BY table_name,index_name,non_unique ORDER BY table_name,index_name;

SELECT code,name,requires_registration,requires_driving_licence,requires_rc,insurance_required,status
FROM `u676721746_essivery`.`delivery_vehicle_types` ORDER BY display_order,name;

SELECT delivery_partner_id,COUNT(*) active_primary_count
FROM `u676721746_essivery`.`delivery_partner_vehicles`
WHERE is_primary=1 AND status='active' GROUP BY delivery_partner_id HAVING COUNT(*)>1;

SELECT version,description FROM `u676721746_essivery`.`schema_migrations` WHERE version='2026_09_01_4f_delivery_setup';

