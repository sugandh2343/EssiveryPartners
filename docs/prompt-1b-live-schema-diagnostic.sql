-- ESSIVERY PARTNER Prompt 1B: read-only production schema diagnostic
-- Safe operations only: information_schema reads, SELECT, SET, PREPARE, EXECUTE.
-- This script does not create or alter schema/data.

SELECT DATABASE() AS database_name, @@hostname AS database_host, @@version AS database_version;

SET @target_tables =
  'users,user_identities,partners,shopkeeper_profile,partner_profile,partner,partner_business_modules,partner_retail_parent_categories,partner_marketplace_settings,partner_service_areas,partner_business_hours,partner_documents,grocery_stores,grocery_store_products,partner_products,grocery_orders,orders,restaurants,restaurant_orders,home_service_provider_profiles,home_service_provider_services,home_service_bookings,delivery_partners,delivery_partner_documents,delivery_partner_vehicles,delivery_partner_availability,delivery_partner_zone_mapping,delivery_assignments,taxi_driver_profiles,taxi_vehicles,taxi_documents,taxi_rides,wallets,wallet_ledger,wallet_holds,partner_order_charges,partner_statements,settlement_statements,file_uploads,partner_referral_leads,partner_referral_mobile_locks,partner_referral_timeline,partner_referral_config,referral_qualifications';

SELECT table_name, engine, table_rows, create_time, update_time
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND FIND_IN_SET(table_name, @target_tables)
ORDER BY table_name;

SELECT table_name, ordinal_position, column_name, column_type, is_nullable,
       column_default, column_key, extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND FIND_IN_SET(table_name, @target_tables)
ORDER BY table_name, ordinal_position;

SELECT table_name, index_name, non_unique, seq_in_index, column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND FIND_IN_SET(table_name, @target_tables)
ORDER BY table_name, index_name, seq_in_index;

SELECT k.table_name, k.column_name, k.constraint_name,
       k.referenced_table_name, k.referenced_column_name,
       r.update_rule, r.delete_rule
FROM information_schema.key_column_usage k
LEFT JOIN information_schema.referential_constraints r
  ON r.constraint_schema = k.constraint_schema
 AND r.constraint_name = k.constraint_name
WHERE k.table_schema = DATABASE()
  AND k.referenced_table_name IS NOT NULL
  AND (FIND_IN_SET(k.table_name, @target_tables)
       OR FIND_IN_SET(k.referenced_table_name, @target_tables))
ORDER BY k.table_name, k.constraint_name, k.ordinal_position;

-- Exact row counts for every target table that actually exists.
SET SESSION group_concat_max_len = 1000000;
SELECT GROUP_CONCAT(
         CONCAT('SELECT ''', table_name, ''' table_name, COUNT(*) row_count FROM `',
                REPLACE(table_name, '`', '``'), '`')
         ORDER BY table_name SEPARATOR ' UNION ALL '
       ) INTO @count_sql
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND FIND_IN_SET(table_name, @target_tables);
SET @count_sql = COALESCE(@count_sql, 'SELECT ''NO_TARGET_TABLES'' table_name, 0 row_count');
PREPARE count_statement FROM @count_sql;
EXECUTE count_statement;
DEALLOCATE PREPARE count_statement;

-- Distinct wallet ownership conventions (guarded for optional table).
SELECT IF(COUNT(*) = 1,
          'SELECT owner_type, wallet_type, COUNT(*) wallet_count, MIN(owner_id) min_owner_id, MAX(owner_id) max_owner_id FROM wallets GROUP BY owner_type, wallet_type ORDER BY owner_type, wallet_type',
          'SELECT ''wallets_missing'' owner_type, NULL wallet_type, 0 wallet_count, NULL min_owner_id, NULL max_owner_id')
INTO @wallet_sql
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'wallets';
PREPARE wallet_statement FROM @wallet_sql;
EXECUTE wallet_statement;
DEALLOCATE PREPARE wallet_statement;

-- Identity distribution and parent-category convention.
SELECT IF(COUNT(*) = 1,
          'SELECT identity_type, parent_category_id, status, COUNT(*) identity_count FROM users GROUP BY identity_type, parent_category_id, status ORDER BY identity_type, parent_category_id, status',
          'SELECT ''users_missing'' identity_type, NULL parent_category_id, NULL status, 0 identity_count')
INTO @identity_sql
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'users';
PREPARE identity_statement FROM @identity_sql;
EXECUTE identity_statement;
DEALLOCATE PREPARE identity_statement;

-- Partner-core linkage coverage without exposing personal fields.
SELECT GROUP_CONCAT(
         CONCAT('SELECT ''', table_name,
                ''' table_name, COUNT(*) rows_total, COUNT(DISTINCT user_id) distinct_user_ids, MIN(id) min_id, MAX(id) max_id FROM `',
                REPLACE(table_name, '`', '``'), '`')
         ORDER BY table_name SEPARATOR ' UNION ALL '
       ) INTO @partner_link_sql
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('partners','shopkeeper_profile','partner_profile','partner','delivery_partners')
  AND column_name = 'user_id';
SET @partner_link_sql = COALESCE(@partner_link_sql, 'SELECT ''NO_PARTNER_CORE_WITH_USER_ID'' table_name, 0 rows_total, 0 distinct_user_ids, NULL min_id, NULL max_id');
PREPARE partner_link_statement FROM @partner_link_sql;
EXECUTE partner_link_statement;
DEALLOCATE PREPARE partner_link_statement;

-- Presence of suspected schema-drift columns used by User repositories.
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND ((table_name = 'partner_marketplace_settings'
        AND column_name IN ('module_code','status','marketplace_visible','operational_status','serviceability_status'))
    OR (table_name = 'partner_service_areas'
        AND column_name IN ('service_area_id','area_type','pincode','latitude','longitude','radius_km','status'))
    OR (table_name = 'grocery_stores'
        AND column_name IN ('owner_user_id','partner_id','approval_status','marketplace_visible','status')))
ORDER BY table_name, column_name;

-- Referral host/config without exposing secure referral tokens.
SELECT IF(COUNT(*) = 1,
          'SELECT id, public_base_url, allow_mobile_auto_claim, validity_days, reward_campaign_id FROM partner_referral_config',
          'SELECT NULL id, ''partner_referral_config_missing'' public_base_url, NULL allow_mobile_auto_claim, NULL validity_days, NULL reward_campaign_id')
INTO @referral_config_sql
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'partner_referral_config';
PREPARE referral_config_statement FROM @referral_config_sql;
EXECUTE referral_config_statement;
DEALLOCATE PREPARE referral_config_statement;

-- Counts showing whether generic and module-specific commerce are populated.
SELECT GROUP_CONCAT(
         CONCAT('SELECT ''', table_name, ''' table_name, COUNT(*) row_count, MAX(updated_at) latest_update FROM `',
                REPLACE(table_name, '`', '``'), '`')
         ORDER BY table_name SEPARATOR ' UNION ALL '
       ) INTO @commerce_sql
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN ('partner_products','grocery_store_products','orders','grocery_orders','restaurant_orders','home_service_bookings','taxi_rides')
  AND column_name = 'updated_at';
SET @commerce_sql = COALESCE(@commerce_sql, 'SELECT ''NO_COMMERCE_TABLES'' table_name, 0 row_count, NULL latest_update');
PREPARE commerce_statement FROM @commerce_sql;
EXECUTE commerce_statement;
DEALLOCATE PREPARE commerce_statement;
