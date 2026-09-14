<?php
declare(strict_types=1);

namespace Essivery\Api\Core {
    final class ApiException extends \RuntimeException
    {
        public function __construct(
            public int $status,
            public string $errorCode,
            string $message,
            public string $userMessage,
            public array $fieldErrors = [],
            public bool $retryable = false,
            public array $metadata = []
        ) { parent::__construct($message); }
    }
    final class Helpers
    {
        public static function publicId(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(12)); }
    }
}

namespace {
    $root = dirname(__DIR__);
    require $root . '/Policies/PartnerIdentityPolicy.php';
    require $root . '/Domain/PartnerContext.php';
    require $root . '/Repositories/PartnerContextRepository.php';
    require $root . '/Services/ReferralAutoClaimInterface.php';
    require $root . '/Services/PartnerReferralService.php';
    require $root . '/Services/PartnerSetupService.php';
    require $root . '/Repositories/PartnerPersonalRepository.php';
    require $root . '/Services/PartnerPersonalService.php';
    require $root . '/Repositories/PartnerBusinessRepository.php';
    require $root . '/Services/PartnerBusinessService.php';
    require $root . '/Repositories/PartnerLocationRepository.php';
    require $root . '/Services/PartnerLocationService.php';
    require $root . '/Repositories/PartnerBusinessHoursRepository.php';
    require $root . '/Services/PartnerBusinessHoursService.php';
    require $root . '/Repositories/PartnerFulfilmentRepository.php';
    require $root . '/Services/PartnerOperationsService.php';
    require $root . '/Repositories/PartnerDocumentRepository.php';
    require $root . '/Services/PartnerDocumentStorage.php';
    require $root . '/Services/PartnerDocumentsService.php';
    require $root . '/Repositories/PartnerBankAccountRepository.php';
    require $root . '/Services/PartnerBankEncryption.php';
    require $root . '/Services/PartnerBankService.php';
    require $root . '/Repositories/PartnerRetailRepository.php';
    require $root . '/Services/PartnerRetailService.php';
    require $root . '/Repositories/PartnerRestaurantRepository.php';
    require $root . '/Services/PartnerRestaurantService.php';
    require $root . '/Repositories/PartnerHomeServiceRepository.php';
    require $root . '/Services/PartnerHomeServiceService.php';
    require $root . '/Repositories/PartnerDeliveryRepository.php';
    require $root . '/Services/PartnerDeliveryService.php';
    require $root . '/Repositories/PartnerReviewRepository.php';
    require $root . '/Services/PartnerReviewService.php';

    use Essivery\Api\Core\ApiException;
    use Essivery\Partner\Domain\PartnerContext;
    use Essivery\Partner\Policies\PartnerIdentityPolicy;
    use Essivery\Partner\Repositories\PartnerContextRepository;
    use Essivery\Partner\Services\PartnerReferralService;
    use Essivery\Partner\Services\PartnerSetupService;
    use Essivery\Partner\Repositories\PartnerPersonalRepository;
    use Essivery\Partner\Services\PartnerPersonalService;
    use Essivery\Partner\Repositories\PartnerBusinessRepository;
    use Essivery\Partner\Services\PartnerBusinessService;
    use Essivery\Partner\Repositories\PartnerLocationRepository;
    use Essivery\Partner\Services\PartnerLocationService;
    use Essivery\Partner\Repositories\PartnerBusinessHoursRepository;
    use Essivery\Partner\Services\PartnerBusinessHoursService;
    use Essivery\Partner\Repositories\PartnerFulfilmentRepository;
    use Essivery\Partner\Services\PartnerOperationsService;
    use Essivery\Partner\Repositories\PartnerDocumentRepository;
    use Essivery\Partner\Services\PartnerDocumentStorage;
    use Essivery\Partner\Services\PartnerDocumentsService;
    use Essivery\Partner\Repositories\PartnerBankAccountRepository;
    use Essivery\Partner\Services\PartnerBankEncryption;
    use Essivery\Partner\Services\PartnerBankService;
    use Essivery\Partner\Repositories\PartnerRetailRepository;
    use Essivery\Partner\Services\PartnerRetailService;
    use Essivery\Partner\Repositories\PartnerRestaurantRepository;
    use Essivery\Partner\Services\PartnerRestaurantService;
    use Essivery\Partner\Repositories\PartnerHomeServiceRepository;
    use Essivery\Partner\Services\PartnerHomeServiceService;
    use Essivery\Partner\Repositories\PartnerDeliveryRepository;
    use Essivery\Partner\Services\PartnerDeliveryService;
    use Essivery\Partner\Repositories\PartnerReviewRepository;
    use Essivery\Partner\Services\PartnerReviewService;

    final class FakeReferralRepository
    {
        public string $mode = 'valid';
        public array $calls = [];
        public function resolve(string $token): array
        {
            return match ($this->mode) {
                'invalid' => ['valid' => false, 'state' => 'INVALID'],
                'expired' => ['valid' => true, 'state' => 'EXPIRED', 'module' => 'RETAIL', 'canRegister' => false],
                'registered' => ['valid' => true, 'state' => 'REGISTERED', 'module' => 'RETAIL', 'canRegister' => false],
                default => ['valid' => true, 'state' => 'REFERRAL_SENT', 'module' => 'RETAIL', 'canRegister' => true],
            };
        }
        public function claim(int $partner, string $mobile, ?string $token = null): array
        {
            $this->calls[] = compact('partner', 'mobile', 'token');
            if ($this->mode === 'wrong_mobile') throw new DomainException('Mobile mismatch');
            if ($this->mode === 'claimed_elsewhere') throw new DomainException('Already claimed');
            if ($this->mode === 'none') throw new OutOfBoundsException('No referral');
            return ['referral_status' => 'REGISTERED', 'business_name' => 'ABC Store', 'business_module' => 'RETAIL', 'pincode' => '226010', 'referrer_user_id' => 99, 'reward_amount_paise' => 5000];
        }
    }

    $tests = 0;
    $assert = static function (bool $condition, string $message) use (&$tests): void {
        $tests++;
        if (!$condition) throw new RuntimeException($message);
    };
    $throws = static function (callable $callback, string $code) use ($assert): void {
        try { $callback(); } catch (ApiException $error) {
            $assert($error->errorCode === $code, "Expected $code, got {$error->errorCode}");
            return;
        }
        throw new RuntimeException("Expected $code to be thrown");
    };

    $config = require $root . '/config/partner.php';
    $policy = new PartnerIdentityPolicy($config['identities']);
    $assert($policy->moduleFor('grocery') === 'RETAIL', 'Grocery must map to RETAIL');
    $assert($policy->moduleFor('restaurant') === 'RESTAURANT', 'Restaurant module mismatch');
    $assert($policy->moduleFor('delivery_partner') === 'DELIVERY', 'Delivery module mismatch');
    $throws(fn () => $policy->moduleFor('customer'), 'PARTNER_IDENTITY_REQUIRED');
    $throws(fn () => $policy->moduleFor('admin'), 'PARTNER_IDENTITY_UNSUPPORTED');
    $policy->assertCategory('grocery', 1, 'grocery');
    $policy->assertCategory('vegetable', 3, 'fresh-fruits-and-vegetables');
    $policy->assertCategory('delivery_partner', 0, null);
    $throws(fn () => $policy->assertCategory('delivery_partner', 1, 'grocery'), 'PARTNER_CONTEXT_AMBIGUOUS');
    $throws(fn () => $policy->assertCategory('grocery', 2, 'restaurant'), 'PARTNER_CONTEXT_AMBIGUOUS');

    $business = new PartnerContext(7, 'USR_grocery', 17, 'IDN_grocery', 'grocery', 1, 'RETAIL', 15, 'PTR_public', null, null, 'pending', 'draft', 'active', 'active');
    $payload = $business->publicPayload();
    $assert($payload['partner']['kind'] === 'business', 'Business context kind mismatch');
    $assert(!str_contains(json_encode($payload), '"authenticatedUserId"'), 'Internal User ID leaked');
    $assert(!str_contains(json_encode($payload), '"businessPartnerId"'), 'Internal Partner ID leaked');

    $delivery = new PartnerContext(20, 'USR_delivery', 30, 'IDN_delivery', 'delivery_partner', 0, 'DELIVERY', null, null, 8, 'DPR_public', 'pending', 'draft', 'active', 'active');
    $assert($delivery->publicPayload()['partner']['kind'] === 'delivery', 'Delivery context kind mismatch');
    $throws(fn () => new PartnerContext(1, 'USR', 2, 'IDN', 'grocery', 1, 'RETAIL', 3, 'PTR', 4, 'DPR', 'pending', 'draft', 'active', 'active'), 'PARTNER_CONTEXT_AMBIGUOUS');
    $throws(fn () => new PartnerContext(1, 'USR', 2, 'IDN', 'grocery', 1, 'RETAIL', null, null, null, null, 'pending', 'draft', 'active', 'active'), 'PARTNER_CONTEXT_AMBIGUOUS');

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,mobile TEXT NOT NULL)');
    $pdo->exec("INSERT INTO users(id,mobile) VALUES(7,'+919876543210'),(8,'+919111111111'),(20,'+919222222222')");
    $pdo->exec("CREATE TABLE partners(id INTEGER PRIMARY KEY,user_id INTEGER,business_name TEXT,owner_name TEXT,mobile TEXT,email TEXT,tax_number TEXT,deleted_at TEXT)");
    $pdo->exec("INSERT INTO partners VALUES(15,7,'Grocery Partner','Partner','+919876543210',NULL,NULL,NULL),(16,8,'Restaurant Partner','Partner','+919111111111',NULL,NULL,NULL)");
    $pdo->exec('ALTER TABLE partners ADD address_line_1 TEXT');
    $pdo->exec('ALTER TABLE partners ADD address_line_2 TEXT');
    $pdo->exec('ALTER TABLE partners ADD locality TEXT');
    $pdo->exec('ALTER TABLE partners ADD landmark TEXT');
    $pdo->exec('ALTER TABLE partners ADD city_id INTEGER');
    $pdo->exec('ALTER TABLE partners ADD state_id INTEGER');
    $pdo->exec('ALTER TABLE partners ADD pincode TEXT');
    $pdo->exec('ALTER TABLE partners ADD latitude TEXT');
    $pdo->exec('ALTER TABLE partners ADD longitude TEXT');
    $pdo->exec("ALTER TABLE partners ADD onboarding_status TEXT DEFAULT 'draft'");
    $pdo->exec('ALTER TABLE partners ADD updated_at TEXT');
    $pdo->exec("INSERT INTO users(id,mobile) VALUES(9,'+919333333333')");
    $pdo->exec("INSERT INTO partners(id,user_id,business_name,owner_name,mobile,deleted_at) VALUES(17,9,'Home Service Partner','Provider','+919333333333',NULL)");
    $pdo->exec('CREATE TABLE parent_categories(id INTEGER PRIMARY KEY,public_id TEXT,name TEXT NOT NULL,slug TEXT NOT NULL,icon_url TEXT,status TEXT DEFAULT \'active\',display_order INTEGER DEFAULT 0,deleted_at TEXT)');
    $pdo->exec("INSERT INTO parent_categories(id,public_id,name,slug,status) VALUES(1,'CAT_grocery','Grocery','grocery','active'),(2,'CAT_restaurants','Restaurants','restaurants','active'),(3,'CAT_vegetable','Fresh Fruits and Vegetables','fresh-fruits-and-vegetables','active'),(4,'CAT_pharmacy','Pharmacy','pharmacy','active'),(5,'CAT_fashion','Fashion','fashion','active'),(6,'CAT_electronics','Electronics','electronics','active')");
    $pdo->exec('CREATE TABLE partner_marketplace_settings(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,partner_id INTEGER UNIQUE,description TEXT,logo_url TEXT)');
    $pdo->exec("CREATE TABLE partner_business_modules(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL,module_code TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,module_code))");
    $pdo->exec("CREATE TABLE partner_retail_parent_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL,parent_category_id INTEGER NOT NULL,catalogue_setup_mode TEXT,retail_confirmed_at TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,parent_category_id))");
    $pdo->exec('CREATE TABLE master_products(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT)');
    $pdo->exec('CREATE TABLE inventory(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,quantity INTEGER)');
    $pdo->exec("CREATE TABLE cuisines(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,status TEXT DEFAULT 'active')");
    $pdo->exec("INSERT INTO cuisines(public_id,name,slug,status) VALUES('CUI_north','North Indian','north-indian','active'),('CUI_chinese','Chinese','chinese','active'),('CUI_dessert','Desserts','desserts','active'),('CUI_south','South Indian','south-indian','active'),('CUI_italian','Italian','italian','active'),('CUI_inactive','Inactive','inactive','inactive')");
    $pdo->exec("CREATE TABLE restaurants(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,owner_user_id INTEGER UNIQUE,name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,city_id INTEGER NOT NULL,operational_status TEXT DEFAULT 'closed',status TEXT DEFAULT 'inactive',deleted_at TEXT,menu_setup_mode TEXT,setup_confirmed_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE restaurant_cuisines(restaurant_id INTEGER NOT NULL,cuisine_id INTEGER NOT NULL,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(restaurant_id,cuisine_id))");
    $pdo->exec("CREATE TABLE home_service_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,display_order INTEGER DEFAULT 0,status TEXT DEFAULT 'inactive')");
    $pdo->exec("CREATE TABLE home_service_subcategories(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,category_id INTEGER NOT NULL,name TEXT NOT NULL,slug TEXT NOT NULL,display_order INTEGER DEFAULT 0,status TEXT DEFAULT 'inactive')");
    $pdo->exec("CREATE TABLE home_service_masters(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,category_id INTEGER NOT NULL,subcategory_id INTEGER,name TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,display_order INTEGER DEFAULT 0,status TEXT DEFAULT 'inactive')");
    $pdo->exec("CREATE TABLE home_service_provider_profiles(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL UNIQUE,provider_type TEXT DEFAULT 'individual',approval_status TEXT DEFAULT 'pending',verification_status TEXT DEFAULT 'not_submitted',operational_status TEXT DEFAULT 'unavailable',marketplace_visible INTEGER DEFAULT 0,status TEXT DEFAULT 'inactive',setup_preference TEXT,setup_confirmed_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE home_service_provider_setup_services(id INTEGER PRIMARY KEY AUTOINCREMENT,provider_id INTEGER NOT NULL,service_id INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(provider_id,service_id))");
    $pdo->exec("CREATE TABLE home_service_provider_services(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,provider_id INTEGER NOT NULL,service_id INTEGER NOT NULL,provider_price REAL NOT NULL,availability TEXT DEFAULT 'unavailable',status TEXT DEFAULT 'inactive')");
    $pdo->exec("INSERT INTO home_service_categories(public_id,name,slug,display_order,status) VALUES('HSC_clean','Cleaning','cleaning',1,'active'),('HSC_inactive','Inactive Category','inactive-category',2,'inactive')");
    $pdo->exec("INSERT INTO home_service_subcategories(public_id,category_id,name,slug,display_order,status) VALUES('HSS_home',1,'Home Cleaning','home-cleaning',1,'active'),('HSS_inactive',1,'Inactive','inactive',2,'inactive')");
    $pdo->exec("INSERT INTO home_service_masters(public_id,category_id,subcategory_id,name,slug,display_order,status) VALUES('HSM_deep',1,1,'Deep Home Cleaning','deep-home-cleaning',1,'active'),('HSM_sofa',1,1,'Sofa Cleaning','sofa-cleaning',2,'active'),('HSM_inactive_service',1,1,'Inactive Service','inactive-service',3,'inactive'),('HSM_inactive_sub',1,2,'Hidden Service','hidden-service',4,'active')");
    $pdo->exec("CREATE TABLE delivery_partners(id INTEGER PRIMARY KEY,user_id INTEGER,name TEXT,mobile TEXT,email TEXT,deleted_at TEXT)");
    $pdo->exec("INSERT INTO delivery_partners VALUES(8,20,'Delivery Partner','+919222222222',NULL,NULL)");
    $pdo->exec('ALTER TABLE delivery_partners ADD address_line TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD address_line_2 TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD locality TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD landmark TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD city_id INTEGER');
    $pdo->exec('ALTER TABLE delivery_partners ADD state_id INTEGER');
    $pdo->exec('ALTER TABLE delivery_partners ADD pincode TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD latitude TEXT');
    $pdo->exec('ALTER TABLE delivery_partners ADD longitude TEXT');
    $pdo->exec("ALTER TABLE delivery_partners ADD approval_status TEXT DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE delivery_partners ADD availability_status TEXT DEFAULT 'offline'");
    $pdo->exec("ALTER TABLE delivery_partners ADD status TEXT DEFAULT 'active'");
    $pdo->exec("ALTER TABLE delivery_partners ADD onboarding_status TEXT DEFAULT 'draft'");
    $pdo->exec('ALTER TABLE delivery_partners ADD updated_at TEXT');
    $pdo->exec("CREATE TABLE delivery_vehicle_types(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,requires_registration INTEGER DEFAULT 1,requires_driving_licence INTEGER DEFAULT 1,requires_rc INTEGER DEFAULT 1,insurance_required INTEGER DEFAULT 0,display_order INTEGER DEFAULT 0,status TEXT DEFAULT 'inactive')");
    $pdo->exec("INSERT INTO delivery_vehicle_types(public_id,code,name,requires_registration,requires_driving_licence,requires_rc,insurance_required,display_order,status) VALUES('DVT_bicycle','bicycle','Bicycle',0,0,0,0,10,'active'),('DVT_motorcycle','motorcycle','Motorcycle',1,1,1,0,20,'active'),('DVT_inactive','inactive','Inactive',1,1,1,0,30,'inactive')");
    $pdo->exec("CREATE TABLE delivery_partner_vehicles(id INTEGER PRIMARY KEY AUTOINCREMENT,delivery_partner_id INTEGER NOT NULL,vehicle_type TEXT NOT NULL,vehicle_make TEXT,vehicle_model TEXT,vehicle_number TEXT,registration_number TEXT,vehicle_ownership TEXT,setup_confirmed_at TEXT,is_primary INTEGER DEFAULT 0,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(delivery_partner_id,is_primary))");
    $pdo->exec("CREATE TABLE states(id INTEGER PRIMARY KEY,name TEXT,status TEXT)");
    $pdo->exec("CREATE TABLE cities(id INTEGER PRIMARY KEY,state_id INTEGER,name TEXT,status TEXT)");
    $pdo->exec("CREATE TABLE pincodes(id INTEGER PRIMARY KEY,city_id INTEGER,service_area_id INTEGER,pincode TEXT,status TEXT)");
    $pdo->exec("INSERT INTO states VALUES(1,'Uttar Pradesh','active'),(2,'Delhi','active')");
    $pdo->exec("INSERT INTO cities VALUES(1,1,'Lucknow','active'),(2,2,'New Delhi','active')");
    $pdo->exec("INSERT INTO pincodes VALUES(1,1,NULL,'226002','active'),(2,1,NULL,'226028','active')");
    $pdo->exec('CREATE TABLE partner_referral_config(id INTEGER PRIMARY KEY,allow_mobile_auto_claim INTEGER NOT NULL)');
    $pdo->exec('INSERT INTO partner_referral_config VALUES(1,1)');
    $pdo->exec('CREATE TABLE partner_referral_leads(id INTEGER PRIMARY KEY,partner_id INTEGER,business_name TEXT,business_module TEXT,locality TEXT,address TEXT,pincode TEXT,latitude REAL,longitude REAL,landmark TEXT,referral_status TEXT)');
    $fake = new FakeReferralRepository();
    $referrals = new PartnerReferralService($pdo, new PartnerContextRepository($pdo), null, $fake);
    $claimed = $referrals->claim($business, str_repeat('a', 43));
    $assert($claimed['claimed'] && $claimed['status'] === 'REGISTERED', 'Explicit referral claim failed');
    $assert($fake->calls[0]['partner'] === 15 && $fake->calls[0]['mobile'] === '+919876543210', 'Claim did not derive Partner/mobile from backend context');
    $assert(!str_contains(json_encode($claimed), 'referrer') && !str_contains(json_encode($claimed), '5000'), 'Safe claim projection leaked referral internals');
    $fake->mode = 'registered';
    $assert($referrals->claim($business, str_repeat('a', 43))['claimed'], 'Repeat claim was not idempotent');
    $fake->mode = 'expired';
    $assert($referrals->claim($business, str_repeat('a', 43))['state'] === 'expired', 'Expired referral result mismatch');
    $fake->mode = 'invalid';
    $assert($referrals->claim($business, str_repeat('a', 43))['state'] === 'invalid', 'Invalid referral result mismatch');
    $fake->mode = 'wrong_mobile';
    $throws(fn () => $referrals->claim($business, str_repeat('a', 43)), 'REFERRAL_NOT_CLAIMABLE');
    $fake->mode = 'claimed_elsewhere';
    $throws(fn () => $referrals->claim($business, str_repeat('a', 43)), 'REFERRAL_NOT_CLAIMABLE');
    $restaurant = new PartnerContext(7, 'USR_restaurant', 18, 'IDN_restaurant', 'restaurant', 2, 'RESTAURANT', 15, 'PTR_public', null, null, 'pending', 'draft', 'active', 'active');
    $fake->mode = 'valid';
    $throws(fn () => $referrals->claim($restaurant, str_repeat('a', 43)), 'REFERRAL_MODULE_MISMATCH');
    $throws(fn () => $referrals->claim($delivery, str_repeat('a', 43)), 'REFERRAL_NOT_APPLICABLE');
    $beforeAuto = count($fake->calls);
    $referrals->afterBootstrap($business);
    $assert(count($fake->calls) === $beforeAuto + 1 && $fake->calls[array_key_last($fake->calls)]['token'] === null, 'Same-mobile auto-claim was not attempted');
    $fake->mode = 'none';
    $referrals->afterBootstrap($business);
    $assert(true, 'No-referral auto-claim must remain non-fatal');

    $pdo->exec("CREATE TABLE partner_onboarding_applications(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,business_partner_id INTEGER,delivery_partner_id INTEGER,identity_type TEXT,module_code TEXT,version_no INTEGER DEFAULT 1,application_status TEXT DEFAULT 'draft',current_step TEXT,completion_percentage INTEGER DEFAULT 0,submitted_at TEXT,last_saved_at TEXT,is_current INTEGER DEFAULT 1,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(business_partner_id,is_current),UNIQUE(delivery_partner_id,is_current))");
    $pdo->exec("CREATE TABLE partner_onboarding_steps(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT UNIQUE,application_id INTEGER,step_code TEXT,requirement_type TEXT,is_required INTEGER,step_status TEXT,completed_at TEXT,last_saved_at TEXT,revision_no INTEGER DEFAULT 1,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(application_id,step_code))");
    $pdo->exec("CREATE TABLE partner_onboarding_status_history(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,application_id INTEGER NOT NULL,from_status TEXT,to_status TEXT NOT NULL,transition_type TEXT NOT NULL,actor_type TEXT NOT NULL,actor_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE partner_business_hours(id INTEGER PRIMARY KEY AUTOINCREMENT,partner_id INTEGER NOT NULL,module_code TEXT NOT NULL DEFAULT 'RETAIL',day_of_week INTEGER NOT NULL,slot_order INTEGER NOT NULL DEFAULT 1,is_open INTEGER NOT NULL DEFAULT 0,is_closed INTEGER NOT NULL DEFAULT 0,is_24_hours INTEGER NOT NULL DEFAULT 0,is_overnight INTEGER NOT NULL DEFAULT 0,opens_at TEXT,closes_at TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,module_code,day_of_week,slot_order))");
    $pdo->exec("CREATE TABLE partner_fulfilment_settings(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL,module_code TEXT NOT NULL,essivery_delivery INTEGER NOT NULL DEFAULT 0,self_delivery INTEGER NOT NULL DEFAULT 0,customer_pickup INTEGER NOT NULL DEFAULT 0,service_at_customer_location INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,module_code))");
    $pdo->exec("CREATE TABLE partner_documents(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL,document_type TEXT NOT NULL,active_document_type TEXT GENERATED ALWAYS AS(CASE WHEN status='active' THEN document_type ELSE NULL END) STORED,file_reference TEXT NOT NULL,review_status TEXT NOT NULL DEFAULT 'pending',review_note TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,active_document_type))");
    $pdo->exec("CREATE TABLE partner_bank_accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,partner_id INTEGER NOT NULL,account_holder_name TEXT NOT NULL,account_number_encrypted BLOB NOT NULL,account_number_last4 TEXT NOT NULL,encryption_version INTEGER NOT NULL DEFAULT 1,ifsc_code TEXT NOT NULL,review_status TEXT NOT NULL DEFAULT 'pending',review_note TEXT,reviewed_by INTEGER,reviewed_at TEXT,status TEXT NOT NULL DEFAULT 'active',active_account_key INTEGER GENERATED ALWAYS AS(CASE WHEN status='active' THEN 1 ELSE NULL END) STORED,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(partner_id,active_account_key))");
    $pdo->exec("CREATE TABLE delivery_partner_bank_accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,delivery_partner_id INTEGER NOT NULL,account_holder_name TEXT NOT NULL,account_number_encrypted BLOB NOT NULL,account_number_last4 TEXT NOT NULL,encryption_version INTEGER NOT NULL DEFAULT 1,ifsc_code TEXT NOT NULL,review_status TEXT NOT NULL DEFAULT 'pending',review_note TEXT,reviewed_by INTEGER,reviewed_at TEXT,status TEXT NOT NULL DEFAULT 'active',active_account_key INTEGER GENERATED ALWAYS AS(CASE WHEN status='active' THEN 1 ELSE NULL END) STORED,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(delivery_partner_id,active_account_key))");
    $pdo->exec("CREATE TABLE delivery_partner_documents(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,delivery_partner_id INTEGER NOT NULL,document_type TEXT NOT NULL,active_document_type TEXT GENERATED ALWAYS AS(CASE WHEN status='active' THEN document_type ELSE NULL END) STORED,file_reference TEXT NOT NULL,review_status TEXT NOT NULL DEFAULT 'pending',review_note TEXT,status TEXT NOT NULL DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(delivery_partner_id,active_document_type))");
    $setupDefinitions = require $root . '/config/setup.php';
    $setups = new PartnerSetupService($pdo, $setupDefinitions);
    $grocerySetup = $setups->bootstrap($business);
    $assert($grocerySetup['created'] === true, 'Grocery setup application was not created');
    $assert($grocerySetup['summary']['completionPercentage'] === 11, 'Grocery setup percentage must be server-calculated as 1 of 9');
    $assert($grocerySetup['summary']['nextRecommendedStep'] === 'personal', 'Grocery next step mismatch');
    $assert(count($grocerySetup['summary']['steps']) === 9 && $grocerySetup['summary']['steps'][0]['status'] === 'complete', 'Grocery setup definition or mobile completion mismatch');
    $repeatSetup = $setups->bootstrap($business);
    $assert($repeatSetup['created'] === false && $repeatSetup['initializedSteps'] === 0, 'Repeated setup bootstrap was not idempotent');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM partner_onboarding_applications WHERE business_partner_id=15')->fetchColumn() === 1, 'Repeated setup created a duplicate application');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM partner_onboarding_steps')->fetchColumn() === 9, 'Repeated setup created duplicate steps');
    $restaurantSetupContext = new PartnerContext(8, 'USR_restaurant', 18, 'IDN_restaurant', 'restaurant', 2, 'RESTAURANT', 16, 'PTR_restaurant', null, null, 'pending', 'draft', 'active', 'active');
    $restaurantSetup = $setups->bootstrap($restaurantSetupContext)['summary'];
    $assert(in_array('restaurantSetup', array_column($restaurantSetup['steps'], 'code'), true), 'Restaurant setup definition missing');
    $deliverySetup = $setups->bootstrap($delivery)['summary'];
    $deliveryCodes = array_column($deliverySetup['steps'], 'code');
    $assert(count($deliveryCodes) === 6 && in_array('deliverySetup', $deliveryCodes, true), 'Delivery setup definition mismatch');
    $assert(!in_array('hours', $deliveryCodes, true) && !in_array('retailSetup', $deliveryCodes, true), 'Delivery setup leaked business-only steps');
    $assert($deliverySetup['completionPercentage'] === 17, 'Delivery setup percentage must be server-calculated as 1 of 6');
    $homeContext = new PartnerContext(9, 'USR_home', 19, 'IDN_home', 'home_service', 3, 'HOME_SERVICE', 17, 'PTR_home', null, null, 'pending', 'draft', 'active', 'active');
    $homeSetup = $setups->bootstrap($homeContext)['summary'];
    $assert(in_array('homeServiceSetup',array_column($homeSetup['steps'],'code'),true),'Home Service setup definition missing');
    $safeSetup = json_encode($setups->load($business));
    $assert(!str_contains($safeSetup, 'application_id') && !str_contains($safeSetup, 'business_partner_id') && !str_contains($safeSetup, 'delivery_partner_id'), 'Setup response leaked internal IDs');
    $personal = new PartnerPersonalService($pdo, new PartnerPersonalRepository($pdo), $setups);
    $assert($personal->get($business)['ownerName'] === '', 'Generated business owner placeholder must not count as Personal Details');
    $savedPersonal = $personal->update($business, ['ownerName' => 'Sugandh Srivastava', 'email' => 'OWNER@EXAMPLE.COM']);
    $assert($savedPersonal['ownerName'] === 'Sugandh Srivastava' && $savedPersonal['email'] === 'owner@example.com', 'Business Personal Details did not persist/normalize');
    $assert($savedPersonal['verifiedMobile'] === '+919876543210' && !isset($savedPersonal['id']), 'Personal Details mobile projection or ID safety failed');
    $assert($savedPersonal['setup']['completionPercentage'] === 22 && $savedPersonal['setup']['nextRecommendedStep'] === 'business', 'Personal completion did not update Grocery setup progress');
    $repeatPersonal = $personal->update($business, ['ownerName' => 'Sugandh Srivastava']);
    $assert($repeatPersonal['setup']['completionPercentage'] === 22, 'Repeat Personal save changed setup progress incorrectly');
    $savedDeliveryPersonal = $personal->update($delivery, ['ownerName' => 'Delivery Owner']);
    $assert($savedDeliveryPersonal['ownerName'] === 'Delivery Owner' && $savedDeliveryPersonal['setup']['nextRecommendedStep'] === 'location', 'Delivery Personal mapping/next step failed');
    $throws(fn () => $personal->update($business, ['ownerName' => '', 'email' => 'bad']), 'VALIDATION_ERROR');
    $businessDetails = new PartnerBusinessService($pdo, new PartnerBusinessRepository($pdo), $setups);
    $assert($businessDetails->get($business)['businessName'] === '', 'Generated business placeholder must not count as Business Details');
    $savedBusiness = $businessDetails->update($business, ['businessName'=>'Fresh Basket','businessContact'=>'98765 43210','description'=>'Fresh essentials delivered locally.','gstNumber'=>'27AAPFU0939F1ZV']);
    $assert($savedBusiness['businessContact']==='+919876543210' && $savedBusiness['gstNumber']==='27AAPFU0939F1ZV', 'Business contact/GST normalization failed');
    $assert($savedBusiness['setup']['completionPercentage']===33 && $savedBusiness['setup']['nextRecommendedStep']==='location', 'Business completion did not update Grocery progress to 33%');
    $assert($businessDetails->update($business, ['businessName'=>'Fresh Basket','businessContact'=>'9876543210'])['setup']['completionPercentage']===33, 'Repeated Business save changed progress');
    $assert(str_contains($businessDetails->descriptionTemplate($restaurantSetupContext)['description'],'delicious food'), 'Restaurant description template mismatch');
    $throws(fn()=>$businessDetails->update($business,['businessName'=>'!','businessContact'=>'123','gstNumber'=>'BAD']), 'VALIDATION_ERROR');
    $throws(fn()=>$businessDetails->update($business,['businessName'=>'<script>alert(1)</script>','businessContact'=>'9876543210']), 'VALIDATION_ERROR');
    $throws(fn()=>$businessDetails->get($delivery), 'PARTNER_SETUP_NOT_APPLICABLE');
    $location=new PartnerLocationService($pdo,new PartnerLocationRepository($pdo),$setups);
    $pdo->exec("INSERT INTO partner_referral_leads(id,partner_id,business_name,business_module,locality,address,pincode,latitude,longitude,landmark,referral_status) VALUES(1,15,'Fresh Basket','RETAIL','Referral Area','Referral Address','226002',26.77,80.95,'Referral Landmark','REGISTERED')");
    $suggestedLocation=$location->get($business);
    $assert(($suggestedLocation['referralSuggestion']['locality']??'')==='Referral Area'&&$suggestedLocation['step']['status']!=='complete','Referral Location suggestion incorrectly completed or failed to prefill');
    $savedLocation=$location->update($business,['addressLine1'=>'B-064, Urban Wood Phase 1','addressLine2'=>'','locality'=>'Sushant Golf City','landmark'=>'Sector C Pocket 7','pincode'=>'226002','city'=>'Lucknow','state'=>'Uttar Pradesh','latitude'=>'26.7701234','longitude'=>'80.9584321','mapConfirmed'=>true]);
    $assert($savedLocation['locality']==='Sushant Golf City'&&$savedLocation['city']['name']==='Lucknow','Grocery Location did not persist/resolve taxonomy');
    $assert($savedLocation['setup']['completionPercentage']===44&&$savedLocation['setup']['nextRecommendedStep']==='hours','Location completion did not update Grocery progress');
    $assert($location->get($business)['latitude']==='26.7701234','Saved Location did not restore on GET');
    $assert($location->get($business)['referralSuggestion']===[],'Official Location did not take precedence over referral suggestion');
    $repeatLocation=$location->update($business,['addressLine1'=>'B-064, Urban Wood Phase 1','addressLine2'=>'Tower 2','locality'=>'Sushant Golf City','landmark'=>'','pincode'=>'226002','city'=>'Lucknow','state'=>'Uttar Pradesh','latitude'=>'26.7701234','longitude'=>'80.9584321','mapConfirmed'=>true]);
    $assert($repeatLocation['setup']['completionPercentage']===44,'Repeated Location save changed progress incorrectly');
    $lucknow226028=$location->update($business,['addressLine1'=>'Hno 1 Gorakhpur Bicchia','addressLine2'=>'','locality'=>'Naubasta Kala Lucknow','landmark'=>'','pincode'=>'226028','city'=>'Lucknow','state'=>'Uttar Pradesh','latitude'=>'26.9044320','longitude'=>'81.0385360','mapConfirmed'=>true]);
    $assert($lucknow226028['pincode']==='226028'&&$lucknow226028['city']['name']==='Lucknow'&&$lucknow226028['state']['name']==='Uttar Pradesh','Confirmed Lucknow 226028 taxonomy did not resolve');
    $unknownPincode=$location->update($restaurantSetupContext,['addressLine1'=>'12 Market Road','addressLine2'=>'','locality'=>'Hazratganj','landmark'=>'','pincode'=>'226099','city'=>'Lucknow','state'=>'Uttar Pradesh','latitude'=>'26.8500000','longitude'=>'80.9500000','mapConfirmed'=>true]);
    $assert($unknownPincode['serviceabilityStatus']==='notConfigured','Unknown valid pincode made a serviceability assumption');
    $deliveryLocation=$location->update($delivery,['addressLine1'=>'44 Delivery Lane','addressLine2'=>'Room 4','locality'=>'New Delhi','landmark'=>'','pincode'=>'110099','city'=>'New Delhi','state'=>'Delhi','latitude'=>'28.6139000','longitude'=>'77.2090000','mapConfirmed'=>true]);
    $assert($deliveryLocation['setup']['nextRecommendedStep']==='documents'&&$deliveryLocation['setup']['completionPercentage']===50,'Delivery Location next step/progress mismatch');
    $assert($pdo->query("SELECT locality FROM delivery_partners WHERE id=8")->fetchColumn()==='New Delhi','Delivery Location did not persist to delivery_partners');
    $throws(fn()=>$location->update($business,['addressLine1'=>'Address','locality'=>'Area','pincode'=>'000000','city'=>'Lucknow','state'=>'Uttar Pradesh','latitude'=>'26','longitude'=>'80','mapConfirmed'=>true]),'VALIDATION_ERROR');
    $throws(fn()=>$location->update($business,['addressLine1'=>'Address','locality'=>'Area','pincode'=>'226002','city'=>'New Delhi','state'=>'Delhi','latitude'=>'91','longitude'=>'181','mapConfirmed'=>false]),'VALIDATION_ERROR');
    $throws(fn()=>$location->update($business,['addressLine1'=>'Address','locality'=>'Area','pincode'=>'226002','city'=>'New Delhi','state'=>'Delhi','latitude'=>'26','longitude'=>'80','mapConfirmed'=>true]),'VALIDATION_ERROR');

    $hours = new PartnerBusinessHoursService($pdo, new PartnerBusinessHoursRepository($pdo), $setups);
    $week = static fn(string $opens='09:00', string $closes='21:00'): array => ['days'=>array_map(static fn(string $day):array=>['day'=>$day,'closed'=>false,'open24Hours'=>false,'slots'=>[['opensAt'=>$opens,'closesAt'=>$closes,'overnight'=>false]]],['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])];
    $assert($hours->get($business)['days']===[], 'New Business Hours must not persist frontend suggestions');
    $savedHours = $hours->update($business, $week());
    $assert(count($savedHours['days'])===7 && $savedHours['setup']['completionPercentage']===56, 'Grocery hours did not persist or update server progress to 56%');
    $assert($savedHours['setup']['nextRecommendedStep']==='operations', 'Hours next step must be Operations');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL'")->fetchColumn()===7, 'Copy-to-all weekly payload did not produce seven canonical rows');
    $closedWeek=$week();$closedWeek['days'][2]=['day'=>'wednesday','closed'=>true,'open24Hours'=>false,'slots'=>[]];$hours->update($business,$closedWeek);
    $closed=$pdo->query("SELECT * FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL' AND day_of_week=2")->fetch();
    $assert((int)$closed['is_closed']===1&&(int)$closed['is_open']===0&&$closed['opens_at']===null&&$closed['closes_at']===null,'Closed Wednesday representation is not canonical');
    $split=$week();$split['days'][0]['slots']=[['opensAt'=>'11:00','closesAt'=>'15:00','overnight'=>false],['opensAt'=>'18:00','closesAt'=>'23:00','overnight'=>false]];$hours->update($business,$split);
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL' AND day_of_week=0")->fetchColumn()===2,'Multiple slots did not persist as normalized rows');
    $overlap=$week();$overlap['days'][0]['slots']=[['opensAt'=>'11:00','closesAt'=>'15:00','overnight'=>false],['opensAt'=>'14:00','closesAt'=>'18:00','overnight'=>false]];$throws(fn()=>$hours->update($business,$overlap),'VALIDATION_ERROR');
    $overnight=$week();$overnight['days'][0]['slots']=[['opensAt'=>'18:00','closesAt'=>'02:00','overnight'=>true]];$hours->update($business,$overnight);
    $assert((int)$pdo->query("SELECT is_overnight FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL' AND day_of_week=0")->fetchColumn()===1,'Overnight schedule was not persisted explicitly');
    $badOvernight=$week();$badOvernight['days'][0]['slots']=[['opensAt'=>'09:00','closesAt'=>'17:00','overnight'=>true]];$throws(fn()=>$hours->update($business,$badOvernight),'VALIDATION_ERROR');
    $allDay=$week();$allDay['days'][6]=['day'=>'sunday','closed'=>false,'open24Hours'=>true,'slots'=>[]];$hours->update($business,$allDay);
    $allDayRow=$pdo->query("SELECT * FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL' AND day_of_week=6")->fetch();
    $assert((int)$allDayRow['is_24_hours']===1&&$allDayRow['opens_at']===null&&$allDayRow['closes_at']===null,'24-hour schedule representation is not canonical');
    $contradictory=$week();$contradictory['days'][0]['open24Hours']=true;$throws(fn()=>$hours->update($business,$contradictory),'VALIDATION_ERROR');
    $allClosed=['days'=>array_map(static fn(string $day):array=>['day'=>$day,'closed'=>true,'open24Hours'=>false,'slots'=>[]],['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])];$throws(fn()=>$hours->update($business,$allClosed),'VALIDATION_ERROR');
    $invalidTime=$week('24:00','21:00');$throws(fn()=>$hours->update($business,$invalidTime),'VALIDATION_ERROR');
    $hours->update($business,$week());$hours->update($business,$week('10:00','20:00'));
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_business_hours WHERE partner_id=15 AND module_code='RETAIL'")->fetchColumn()===7,'Repeated save appended duplicate active hours');
    $assert($hours->get($business)['days'][0]['slots'][0]['opensAt']==='10:00','Existing normalized schedule GET did not restore official rows');
    $restaurantHours=$week();$restaurantHours['days'][0]['slots']=[['opensAt'=>'11:00','closesAt'=>'15:00','overnight'=>false],['opensAt'=>'18:00','closesAt'=>'02:00','overnight'=>true]];$hours->update($restaurantSetupContext,$restaurantHours);
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_business_hours WHERE partner_id=16 AND module_code='RESTAURANT'")->fetchColumn()===8,'Restaurant module scope or split/overnight schedule failed');
    $throws(fn()=>$hours->get($delivery),'PARTNER_SETUP_NOT_APPLICABLE');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_business_hours WHERE partner_id=8")->fetchColumn()===0,'Delivery created Business Hours rows');
    $safeHours=json_encode($hours->get($business));
    foreach(['partner_id','application_id','slot_order','module_code']as$internal)$assert(!str_contains($safeHours,$internal),"Business Hours response leaked $internal");

    $operations=new PartnerOperationsService($pdo,new PartnerFulfilmentRepository($pdo),$setups);
    $assert($operations->get($business)['fulfilment']===['essiveryDelivery'=>false,'selfDelivery'=>false,'customerPickup'=>false],'Unsaved Retail Operations defaults mismatch');
    $marketplaceBefore=(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn();
    $groceryOperations=$operations->update($business,['essiveryDelivery'=>true,'selfDelivery'=>false,'customerPickup'=>false]);
    $assert($groceryOperations['fulfilment']['essiveryDelivery']===true&&$groceryOperations['setup']['completionPercentage']===67,'Grocery Operations did not persist or update progress to 67%');
    $assert($groceryOperations['setup']['nextRecommendedStep']==='documents','Operations next step must be Documents');
    $retailRow=$pdo->query("SELECT * FROM partner_fulfilment_settings WHERE partner_id=15 AND module_code='RETAIL'")->fetch();
    $assert((int)$retailRow['essivery_delivery']===1,'Grocery module was not saved as RETAIL');
    $updatedOperations=$operations->update($business,['essiveryDelivery'=>false,'selfDelivery'=>true,'customerPickup'=>true]);
    $assert($updatedOperations['fulfilment']['selfDelivery']&&$updatedOperations['fulfilment']['customerPickup'],'Self Delivery plus Pickup was not accepted');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_fulfilment_settings WHERE partner_id=15 AND module_code='RETAIL'")->fetchColumn()===1,'Operations update appended a duplicate row');
    $throws(fn()=>$operations->update($business,['essiveryDelivery'=>false,'selfDelivery'=>false,'customerPickup'=>false]),'VALIDATION_ERROR');
    $restaurantOperations=$operations->update($restaurantSetupContext,['essiveryDelivery'=>true,'selfDelivery'=>false,'customerPickup'=>true]);
    $assert($restaurantOperations['type']==='restaurant'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_fulfilment_settings WHERE partner_id=16 AND module_code='RESTAURANT'")->fetchColumn()===1,'Restaurant Operations module scope failed');
    $samePartnerRestaurant=new PartnerContext(7,'USR_restaurant_same',18,'IDN_restaurant_same','restaurant',2,'RESTAURANT',15,'PTR_public',null,null,'pending','draft','active','active');
    $operations->update($samePartnerRestaurant,['essiveryDelivery'=>true,'selfDelivery'=>false,'customerPickup'=>true]);
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_fulfilment_settings WHERE partner_id=15 AND module_code IN('RETAIL','RESTAURANT')")->fetchColumn()===2,'Same Partner Retail and Restaurant Operations were not module-isolated');
    $homeOperations=$operations->update($homeContext,['serviceAtCustomerLocation'=>true]);
    $assert($homeOperations['type']==='home_service'&&$homeOperations['fulfilment']===['serviceAtCustomerLocation'=>true],'Home Service Operations did not persist safely');
    $throws(fn()=>$operations->update($homeContext,['serviceAtCustomerLocation'=>false]),'VALIDATION_ERROR');
    $throws(fn()=>$operations->update($homeContext,['serviceAtCustomerLocation'=>true,'customerPickup'=>true]),'VALIDATION_ERROR');
    $throws(fn()=>$operations->update($business,['essiveryDelivery'=>true,'selfDelivery'=>false,'customerPickup'=>false,'serviceAtCustomerLocation'=>true]),'VALIDATION_ERROR');
    $throws(fn()=>$operations->update($business,['essiveryDelivery'=>true,'selfDelivery'=>false,'customerPickup'=>false,'partnerId'=>15]),'VALIDATION_ERROR');
    $throws(fn()=>$operations->get($delivery),'PARTNER_SETUP_NOT_APPLICABLE');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_fulfilment_settings WHERE partner_id=8")->fetchColumn()===0,'Delivery created merchant fulfilment settings');
    $assert($operations->get($business)['fulfilment']['selfDelivery']===true,'Operations GET did not restore canonical saved values');
    $safeOperations=json_encode($operations->get($business));
    foreach(['partner_id','user_id','module_code','public_id']as$internal)$assert(!str_contains($safeOperations,$internal),"Operations response leaked $internal");
    $assert((int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn()===$marketplaceBefore,'Operations modified partner_marketplace_settings');

    $documentTestRoot=sys_get_temp_dir().DIRECTORY_SEPARATOR.'essivery-doc-tests-'.bin2hex(random_bytes(8));
    $documentConfig=['root'=>$documentTestRoot,'max_bytes'=>5*1024*1024,'mime_types'=>['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf']];
    $documentStorage=new PartnerDocumentStorage($documentConfig);$documentService=new PartnerDocumentsService($pdo,new PartnerDocumentRepository($pdo),$documentStorage,$setups);
    $assert(count($documentService->get($business)['requirements'])===3&&$documentService->get($business)['uploadedCount']===0,'Grocery Documents GET requirements mismatch');
    $assert(count($documentService->get($delivery)['requirements'])===3,'Delivery Documents GET requirements mismatch');
    $makeUpload=static function(string$bytes,string$name)use($documentTestRoot):array{if(!is_dir($documentTestRoot))mkdir($documentTestRoot,0700,true);$tmp=$documentTestRoot.DIRECTORY_SEPARATOR.'incoming-'.bin2hex(random_bytes(8));file_put_contents($tmp,$bytes);return['name'=>$name,'type'=>'application/octet-stream','tmp_name'=>$tmp,'error'=>UPLOAD_ERR_OK,'size'=>filesize($tmp)];};
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    $jpeg=base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==');
    $webp=base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==');$pdf="%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
    $pngState=$documentService->upload($business,'PAN',$makeUpload($png,'../../evil.php'));$assert($pngState['requirements'][2]['uploaded']&&$pngState['requirements'][2]['reviewStatus']==='pending','Valid PNG/PAN upload failed');
    $panReference=$pngState['requirements'][2]['document']['reference'];$assert(str_starts_with($panReference,'DOC_')&&!str_contains(json_encode($pngState),$documentTestRoot),'Document response leaked path or unsafe reference');
    $documentService->upload($business,'AADHAAR_FRONT',$makeUpload($jpeg,'front.jpg'));
    $completeDocuments=$documentService->upload($business,'AADHAAR_BACK',$makeUpload($webp,'back.webp'));
    $assert($completeDocuments['uploadedCount']===3&&$completeDocuments['setup']['completionPercentage']===78,'All Business documents did not complete setup at server-derived progress');
    $assert($completeDocuments['setup']['nextRecommendedStep']==='bank','Business Documents next step must be Bank');
    $fileResult=$documentService->file($business,$panReference);$assert($fileResult['mime']==='image/png'&&is_file($fileResult['path']),'Authenticated document lookup MIME/path failed');
    $documentService->upload($delivery,'PAN',$makeUpload($pdf,'pan.pdf'));$documentService->upload($delivery,'AADHAAR_FRONT',$makeUpload($png,'front.png'));$deliveryDocuments=$documentService->upload($delivery,'AADHAAR_BACK',$makeUpload($png,'back.png'));
    $assert($deliveryDocuments['uploadedCount']===3&&$deliveryDocuments['setup']['completionPercentage']===67&&$deliveryDocuments['setup']['nextRecommendedStep']==='bank','Delivery Documents progress/next step mismatch');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_documents WHERE delivery_partner_id=8')->fetchColumn()===3,'Delivery documents used the wrong ownership table');
    $throws(fn()=>$documentService->upload($business,'UNKNOWN',$makeUpload($png,'x.png')),'VALIDATION_ERROR');
    $throws(fn()=>$documentService->upload($business,'PAN',['error'=>UPLOAD_ERR_NO_FILE]),'DOCUMENT_UPLOAD_FAILED');
    $throws(fn()=>$documentService->upload($business,'PAN',$makeUpload('<?php echo 1;','fake.jpg')),'VALIDATION_ERROR');
    $oversized=$makeUpload(str_repeat('A',5*1024*1024+1),'large.pdf');$throws(fn()=>$documentService->upload($business,'PAN',$oversized),'DOCUMENT_TOO_LARGE');
    $throws(fn()=>$documentService->file($business,'15'),'DOCUMENT_NOT_FOUND');$throws(fn()=>$documentService->file($restaurantSetupContext,$panReference),'DOCUMENT_NOT_FOUND');$throws(fn()=>$documentService->file($delivery,$panReference),'DOCUMENT_NOT_FOUND');
    $pdo->exec("UPDATE partner_documents SET review_status='correction_required' WHERE partner_id=15 AND document_type='PAN' AND status='active'");$correction=$documentService->get($business);
    $assert($correction['requirements'][2]['reviewStatus']==='needs_correction'&&!$correction['requirements'][2]['countsTowardCompletion']&&$correction['setup']['completionPercentage']===67,'Correction did not regress Documents progress');
    $replacement=$documentService->upload($business,'PAN',$makeUpload($pdf,'replacement.pdf'));
    $assert($replacement['requirements'][2]['reviewStatus']==='pending'&&$replacement['setup']['completionPercentage']===78,'Replacement did not reset review and restore completion');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_documents WHERE partner_id=15 AND document_type='PAN' AND status='active'")->fetchColumn()===1&&(int)$pdo->query("SELECT COUNT(*) FROM partner_documents WHERE partner_id=15 AND document_type='PAN' AND status='inactive'")->fetchColumn()===1,'Replacement active uniqueness/history failed');
    $documentService->upload($restaurantSetupContext,'PAN',$makeUpload($png,'restaurant.png'));$assert((int)$pdo->query("SELECT COUNT(*) FROM partner_documents WHERE partner_id=16 AND document_type='PAN'")->fetchColumn()===1,'Business identity documents were not Partner-isolated');

    $testSecret='test-only-bank-secret-material-32-chars-minimum';$crypto=new PartnerBankEncryption($testSecret);$banks=new PartnerBankAccountRepository($pdo);$bankService=new PartnerBankService($pdo,$banks,new PartnerDocumentRepository($pdo),$crypto,$setups);
    $emptyBank=$bankService->get($business);$assert($emptyBank['bank']['hasBankDetails']===false&&$emptyBank['bank']['maskedAccountNumber']===null&&$emptyBank['bankProof']['uploaded']===false&&$emptyBank['step']['status']!=='complete','Bank GET empty state unsafe');
    $cipherOne=$crypto->encrypt('123456789012');$cipherTwo=$crypto->encrypt('123456789012');$assert($cipherOne!==$cipherTwo&&$crypto->decrypt($cipherOne,1)==='123456789012','Bank AES-GCM random-IV roundtrip failed');
    $corrupt=$cipherOne;$corrupt[strlen($corrupt)-1]=chr(ord($corrupt[strlen($corrupt)-1])^1);$throws(fn()=>$crypto->decrypt($corrupt,1),'BANK_CIPHERTEXT_INVALID');
    $throws(fn()=>new PartnerBankEncryption('short'),'BANK_ENCRYPTION_UNAVAILABLE');
    $documentService->upload($business,'BANK_PROOF',$makeUpload($png,'cancelled-cheque.png'));
    $validBank=['accountHolderName'=>'  Test   Partner  ','accountNumber'=>'123456789012','confirmAccountNumber'=>'123456789012','ifsc'=>'hdfc0001234'];$savedBank=$bankService->update($business,$validBank);
    $assert($savedBank['bank']['accountHolderName']==='Test Partner'&&$savedBank['bank']['ifsc']==='HDFC0001234'&&$savedBank['bank']['last4']==='9012','Bank normalization/save failed');
    $assert($savedBank['bankProof']['uploaded']===true&&$savedBank['bankProof']['fileType']==='image'&&$savedBank['step']['status']==='complete','Bank proof did not complete the Bank step');
    $firstProof=$savedBank['bankProof']['reference'];$documentService->upload($business,'BANK_PROOF',$makeUpload($pdf,'passbook.pdf'));$replacedProof=$bankService->get($business);$assert($replacedProof['bankProof']['reference']!==$firstProof&&$replacedProof['bankProof']['fileType']==='pdf'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_documents WHERE partner_id=15 AND document_type='BANK_PROOF' AND status='active'")->fetchColumn()===1&&(int)$pdo->query("SELECT COUNT(*) FROM partner_documents WHERE partner_id=15 AND document_type='BANK_PROOF' AND status='inactive'")->fetchColumn()===1,'Bank proof replacement/history failed');
    $safeBank=json_encode($savedBank);$assert(!str_contains($safeBank,'123456789012')&&!str_contains($safeBank,'account_number_encrypted'),'Bank response leaked sensitive data');
    $dbCipher=$pdo->query("SELECT account_number_encrypted FROM partner_bank_accounts WHERE partner_id=15 AND status='active'")->fetchColumn();$assert($dbCipher!=='123456789012','Raw Bank account persisted');
    $assert($savedBank['setup']['completionPercentage']===89&&$savedBank['setup']['nextRecommendedStep']==='retailSetup','Business Bank progress/next mismatch');
    $sameBank=$bankService->update($business,$validBank);$assert((int)$pdo->query('SELECT COUNT(*) FROM partner_bank_accounts WHERE partner_id=15')->fetchColumn()===1,'Identical Bank resave created history');
    $changedBank=$validBank;$changedBank['accountNumber']=$changedBank['confirmAccountNumber']='999999999999';$bankService->update($business,$changedBank);$assert((int)$pdo->query("SELECT COUNT(*) FROM partner_bank_accounts WHERE partner_id=15 AND status='active'")->fetchColumn()===1&&(int)$pdo->query("SELECT COUNT(*) FROM partner_bank_accounts WHERE partner_id=15 AND status='inactive'")->fetchColumn()===1,'Bank replacement history/current uniqueness failed');
    $pdo->exec("UPDATE partner_bank_accounts SET review_status='correction_required' WHERE partner_id=15 AND status='active'");$bankCorrection=$bankService->get($business);$assert($bankCorrection['bank']['reviewStatus']==='needs_correction'&&$bankCorrection['step']['status']==='needsCorrection'&&$bankCorrection['setup']['completionPercentage']===78,'Bank correction did not regress setup');
    $resubmitted=$bankService->update($business,$changedBank);$assert($resubmitted['bank']['reviewStatus']==='pending'&&$resubmitted['setup']['completionPercentage']===89,'Correction resubmission did not return pending');
    $corrected=$changedBank;$corrected['ifsc']='ICIC0004321';$correctedResult=$bankService->update($business,$corrected);$assert($correctedResult['bank']['reviewStatus']==='pending'&&$correctedResult['setup']['completionPercentage']===89,'Changed correction resubmission did not return pending');
    foreach([['accountNumber'=>'123'],['accountNumber'=>'12A456','confirmAccountNumber'=>'12A456'],['confirmAccountNumber'=>'000000'],['ifsc'=>'BAD']]as$bad){$throws(fn()=>$bankService->update($business,array_replace($validBank,$bad)),'VALIDATION_ERROR');}
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id IN(2,4) AND step_code IN('mobile_verification','personal','business','location','hours','operations','documents')");
    $restaurantBeforeProof=$bankService->update($restaurantSetupContext,['accountHolderName'=>'Restaurant Owner','accountNumber'=>'222222222222','confirmAccountNumber'=>'222222222222','ifsc'=>'SBIN0001234']);$assert($restaurantBeforeProof['step']['status']!=='complete'&&$restaurantBeforeProof['setup']['nextRecommendedStep']==='bank','Bank details incorrectly completed without proof');$documentService->upload($restaurantSetupContext,'BANK_PROOF',$makeUpload($png,'restaurant-cheque.png'));$restaurantBank=$bankService->get($restaurantSetupContext);$assert($bankService->get($business)['bank']['last4']==='9999'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_bank_accounts WHERE partner_id=16 AND status='active'")->fetchColumn()===1,'Restaurant and Grocery Bank ownership leaked');$assert($restaurantBank['setup']['nextRecommendedStep']==='restaurantSetup','Restaurant Bank next mismatch');
    $documentService->upload($homeContext,'BANK_PROOF',$makeUpload($png,'home-cheque.png'));$homeBank=$bankService->update($homeContext,['accountHolderName'=>'Home Provider','accountNumber'=>'333333333333','confirmAccountNumber'=>'333333333333','ifsc'=>'UTIB0001234']);$assert($homeBank['setup']['nextRecommendedStep']==='homeServiceSetup','Home Service Bank next mismatch');
    $documentService->upload($delivery,'BANK_PROOF',$makeUpload($png,'delivery-cheque.png'));$deliveryBank=$bankService->update($delivery,['accountHolderName'=>'Delivery Partner','accountNumber'=>'444444444444','confirmAccountNumber'=>'444444444444','ifsc'=>'KKBK0001234']);$assert($deliveryBank['setup']['completionPercentage']===83&&$deliveryBank['setup']['nextRecommendedStep']==='deliverySetup','Delivery Bank progress/next mismatch');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_bank_accounts WHERE delivery_partner_id=8')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM partner_bank_accounts WHERE partner_id=8')->fetchColumn()===0,'Business/Delivery Bank ownership crossed');

    $pdo->exec("INSERT INTO users(id,mobile) VALUES(10,'+919444444444'),(11,'+919555555555'),(12,'+919666666666'),(13,'+919876543210')");
    $pdo->exec("INSERT INTO partners(id,user_id,business_name,owner_name,mobile,deleted_at) VALUES(18,10,'Vegetable Store','Owner','+919444444444',NULL),(19,11,'Pharmacy Store','Owner','+919555555555',NULL),(20,12,'Fashion Store','Owner','+919666666666',NULL),(21,13,'Electronics Store','Owner','+919777777777',NULL)");
    $vegetable=new PartnerContext(10,'USR_vegetable',20,'IDN_vegetable','vegetable',3,'RETAIL',18,'PTR_vegetable',null,null,'pending','draft','active','active');
    $pharmacy=new PartnerContext(11,'USR_pharmacy',21,'IDN_pharmacy','pharmacy',4,'RETAIL',19,'PTR_pharmacy',null,null,'pending','draft','active','active');
    $fashion=new PartnerContext(12,'USR_fashion',22,'IDN_fashion','fashion',5,'RETAIL',20,'PTR_fashion',null,null,'pending','draft','active','active');
    $electronics=new PartnerContext(13,'USR_electronics',23,'IDN_electronics','electronics',6,'RETAIL',21,'PTR_electronics',null,null,'pending','draft','active','active');
    foreach([$vegetable,$pharmacy,$fashion,$electronics]as$retailContext)$setups->bootstrap($retailContext);
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id IN(5,6,7,8) AND step_code<>'retail_setup'");
    $pdo->exec("INSERT INTO partner_retail_parent_categories(public_id,partner_id,parent_category_id,status) VALUES('PRC_legacy_vegetable',18,3,'active')");
    $retailService=new PartnerRetailService($pdo,new PartnerRetailRepository($pdo),$setups);
    $categoryExpectations=[[$business,'grocery','Grocery'],[$vegetable,'vegetable','Fresh Fruits and Vegetables'],[$pharmacy,'pharmacy','Pharmacy'],[$fashion,'fashion','Fashion'],[$electronics,'electronics','Electronics']];
    foreach($categoryExpectations as[$ctx,$code,$name]){$projection=$retailService->get($ctx);$assert($projection['retail']['category']['code']===$code&&$projection['retail']['category']['name']===$name,"$code Retail category projection mismatch");$assert(!preg_match('/partnerId|userId|parentCategoryId|\"id\"/',json_encode($projection)),"$code Retail projection leaked numeric IDs");}
    $throws(fn()=>$retailService->update($business,['catalogueSetupMode'=>'SELF','confirmed'=>false]),'VALIDATION_ERROR');$throws(fn()=>$retailService->update($business,['catalogueSetupMode'=>'UNKNOWN','confirmed'=>true]),'VALIDATION_ERROR');
    $productsBefore=(int)$pdo->query('SELECT COUNT(*) FROM master_products')->fetchColumn();$inventoryBefore=(int)$pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();$marketplaceRetailBefore=(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn();
    $groceryRetail=$retailService->update($business,['catalogueSetupMode'=>'self','confirmed'=>true]);$assert($groceryRetail['retail']['catalogueSetupMode']==='SELF'&&$groceryRetail['retail']['confirmed']&&$groceryRetail['setup']['completionPercentage']===100,'Grocery Retail completion failed');$assert($groceryRetail['setup']['nextRecommendedStep']===null&&$groceryRetail['setup']['canSubmit']===true,'Retail completion state malformed');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_retail_parent_categories WHERE partner_id=15 AND parent_category_id=1")->fetchColumn()===1,'Missing Grocery mapping was not created deterministically');
    foreach([[$vegetable,'SELF'],[$pharmacy,'ASSISTED'],[$fashion,'SELF'],[$electronics,'ASSISTED']]as[$ctx,$mode]){$result=$retailService->update($ctx,['catalogueSetupMode'=>$mode,'confirmed'=>true]);$assert($result['retail']['catalogueSetupMode']===$mode&&$result['setup']['completionPercentage']===100,$ctx->identityType.' Retail save failed');}
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partner_retail_parent_categories WHERE partner_id=18 AND parent_category_id=3")->fetchColumn()===1,'Existing Retail mapping duplicated');
    $retailService->update($vegetable,['catalogueSetupMode'=>'ASSISTED','confirmed'=>true]);$assert((int)$pdo->query("SELECT COUNT(*) FROM partner_retail_parent_categories WHERE partner_id=18")->fetchColumn()===1&&$retailService->get($vegetable)['retail']['catalogueSetupMode']==='ASSISTED','Retail update/refresh restoration failed');
    $assert($retailService->get($business)['retail']['catalogueSetupMode']==='SELF'&&$retailService->get($electronics)['retail']['catalogueSetupMode']==='ASSISTED','Same-mobile/multi-identity Retail isolation failed');
    $throws(fn()=>$retailService->get($restaurantSetupContext),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$retailService->get($homeContext),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$retailService->get($delivery),'PARTNER_SETUP_NOT_APPLICABLE');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM master_products')->fetchColumn()===$productsBefore&&(int)$pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn()===$inventoryBefore,'Retail Setup created catalogue/inventory rows');$assert((int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn()===$marketplaceRetailBefore,'Retail Setup changed marketplace settings');
    $pdo->exec("UPDATE partner_documents SET review_status='correction_required' WHERE partner_id=15 AND document_type='PAN' AND status='active'");$regressedAfterRetail=$documentService->get($business);$assert($regressedAfterRetail['setup']['completionPercentage']===89&&$regressedAfterRetail['setup']['nextRecommendedStep']==='documents','Earlier correction did not regress completed Retail setup progress');

    $restaurantService=new PartnerRestaurantService($pdo,new PartnerRestaurantRepository($pdo),$setups);$emptyRestaurant=$restaurantService->get($restaurantSetupContext);$assert($emptyRestaurant['restaurant']['cuisines']===[]&&$emptyRestaurant['restaurant']['menuSetupMode']===null&&!$emptyRestaurant['restaurant']['confirmed'],'Restaurant empty state mismatch');$assert(count($emptyRestaurant['options']['cuisines'])===5&&$emptyRestaurant['options']['maximumCuisines']===5,'Restaurant active cuisine options mismatch');
    $throws(fn()=>$restaurantService->update($restaurantSetupContext,['cuisines'=>[],'menuSetupMode'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$restaurantService->update($restaurantSetupContext,['cuisines'=>['unknown'],'menuSetupMode'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$restaurantService->update($restaurantSetupContext,['cuisines'=>['north-indian'],'menuSetupMode'=>'BAD','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$restaurantService->update($restaurantSetupContext,['cuisines'=>['north-indian'],'menuSetupMode'=>'SELF','confirmed'=>false]),'VALIDATION_ERROR');$throws(fn()=>$restaurantService->update($restaurantSetupContext,['cuisines'=>['north-indian','chinese','desserts','south-indian','italian','extra'],'menuSetupMode'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=2 AND step_code<>'restaurant_setup'");$menuBefore=(int)$pdo->query('SELECT COUNT(*) FROM master_products')->fetchColumn();$marketBeforeRestaurant=(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn();$savedRestaurant=$restaurantService->update($restaurantSetupContext,['cuisines'=>['north-indian','chinese','north-indian'],'menuSetupMode'=>'self','confirmed'=>true]);$assert($savedRestaurant['restaurant']['menuSetupMode']==='SELF'&&count($savedRestaurant['restaurant']['cuisines'])===2&&$savedRestaurant['setup']['completionPercentage']===100,'Restaurant save/dedup/progress failed');$assert((int)$pdo->query("SELECT COUNT(*) FROM restaurants WHERE owner_user_id=8 AND status='inactive' AND operational_status='closed'")->fetchColumn()===1&&(int)$pdo->query("SELECT COUNT(*) FROM restaurant_cuisines WHERE status='active'")->fetchColumn()===2,'Restaurant canonical rows/status mismatch');
    $updatedRestaurant=$restaurantService->update($restaurantSetupContext,['cuisines'=>['desserts'],'menuSetupMode'=>'ASSISTED','confirmed'=>true]);$assert($updatedRestaurant['restaurant']['menuSetupMode']==='ASSISTED'&&$updatedRestaurant['restaurant']['cuisines'][0]['code']==='desserts'&&(int)$pdo->query("SELECT COUNT(*) FROM restaurant_cuisines WHERE status='active'")->fetchColumn()===1,'Restaurant cuisine atomic replacement failed');$assert(!preg_match('/restaurantId|partnerId|userId|\"id\"/',json_encode($updatedRestaurant)),'Restaurant response leaked numeric IDs');
    $throws(fn()=>$restaurantService->get($business),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$restaurantService->get($homeContext),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$restaurantService->get($delivery),'PARTNER_SETUP_NOT_APPLICABLE');$assert((int)$pdo->query('SELECT COUNT(*) FROM restaurants')->fetchColumn()===1,'Non-Restaurant access created Restaurant rows');$assert((int)$pdo->query('SELECT COUNT(*) FROM master_products')->fetchColumn()===$menuBefore&&(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn()===$marketBeforeRestaurant,'Restaurant Setup crossed menu/marketplace boundaries');
    $pdo->exec("UPDATE partner_documents SET review_status='correction_required' WHERE partner_id=16 AND document_type='PAN' AND status='active'");$restaurantRegression=$documentService->get($restaurantSetupContext);$assert($restaurantRegression['setup']['completionPercentage']<100&&$restaurantRegression['setup']['nextRecommendedStep']==='documents','Restaurant readiness did not regress after correction');

    $homeService=new PartnerHomeServiceService($pdo,new PartnerHomeServiceRepository($pdo),$setups);$emptyHome=$homeService->get($homeContext);$assert($emptyHome['homeService']['services']===[]&&$emptyHome['homeService']['setupPreference']===null&&!$emptyHome['homeService']['confirmed'],'Home Service empty state mismatch');$assert(count($emptyHome['options']['groups'])===1&&count($emptyHome['options']['groups'][0]['services'])===2&&$emptyHome['options']['maximumServices']===20,'Home Service active grouped options mismatch');
    $throws(fn()=>$homeService->update($homeContext,['services'=>[],'setupPreference'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$homeService->update($homeContext,['services'=>['inactive-service'],'setupPreference'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$homeService->update($homeContext,['services'=>['deep-home-cleaning'],'setupPreference'=>'BAD','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$homeService->update($homeContext,['services'=>['deep-home-cleaning'],'setupPreference'=>'SELF','confirmed'=>false]),'VALIDATION_ERROR');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=4 AND step_code<>'home_service_setup'");$providerServicesBefore=(int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_services')->fetchColumn();$marketBeforeHome=(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn();$savedHome=$homeService->update($homeContext,['services'=>['deep-home-cleaning','sofa-cleaning','deep-home-cleaning'],'setupPreference'=>'self','confirmed'=>true]);$assert($savedHome['homeService']['setupPreference']==='SELF'&&count($savedHome['homeService']['services'])===2&&$savedHome['setup']['completionPercentage']===100,'Home Service save/dedup/progress failed');$assert((int)$pdo->query("SELECT COUNT(*) FROM home_service_provider_profiles WHERE partner_id=17 AND status='inactive' AND operational_status='unavailable' AND marketplace_visible=0")->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_setup_services')->fetchColumn()===2,'Home Service canonical draft persistence mismatch');
    $auditMeta=null;$updatedHome=$homeService->update($homeContext,['services'=>['sofa-cleaning'],'setupPreference'=>'ASSISTED','confirmed'=>true],function($meta)use(&$auditMeta){$auditMeta=$meta;});$assert($updatedHome['homeService']['setupPreference']==='ASSISTED'&&$updatedHome['homeService']['services'][0]['code']==='sofa-cleaning'&&(int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_setup_services')->fetchColumn()===1,'Home Service selection replacement failed');$assert(!preg_match('/providerId|partnerId|serviceId|userId|"id"/',json_encode($updatedHome)),'Home Service response leaked numeric IDs');$assert($auditMeta['serviceCodes']===['sofa-cleaning']&&$auditMeta['serviceCount']===1&&!preg_match('/providerId|partnerId|price/',json_encode($auditMeta)),'Home Service audit metadata was not safe');
    for($i=1;$i<=21;$i++)$pdo->exec("INSERT INTO home_service_masters(public_id,category_id,subcategory_id,name,slug,display_order,status) VALUES('HSM_limit_$i',1,1,'Limit Service $i','limit-service-$i',".(10+$i).",'active')");$tooMany=array_map(fn($i)=>"limit-service-$i",range(1,21));$throws(fn()=>$homeService->update($homeContext,['services'=>$tooMany,'setupPreference'=>'SELF','confirmed'=>true]),'VALIDATION_ERROR');
    $throws(fn()=>$homeService->get($business),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$homeService->get($restaurantSetupContext),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$homeService->get($delivery),'PARTNER_SETUP_NOT_APPLICABLE');$assert((int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_profiles')->fetchColumn()===1,'Non-Home access created provider rows');$assert((int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_services')->fetchColumn()===$providerServicesBefore&&(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn()===$marketBeforeHome,'Home Service Setup crossed pricing/marketplace boundaries');
    $pdo->exec("INSERT INTO partners(id,user_id,business_name,owner_name,mobile,deleted_at) VALUES(22,7,'Same Mobile Home Grocery','Owner','+919876543210',NULL),(23,8,'Same Mobile Home Restaurant','Owner','+919111111111',NULL)");$sameGroceryHome=new PartnerContext(7,'USR_same_grocery_home',27,'IDN_same_grocery_home','home_service',3,'HOME_SERVICE',22,'PTR_same_grocery_home',null,null,'pending','draft','active','active');$sameRestaurantHome=new PartnerContext(8,'USR_same_restaurant_home',28,'IDN_same_restaurant_home','home_service',3,'HOME_SERVICE',23,'PTR_same_restaurant_home',null,null,'pending','draft','active','active');$setups->bootstrap($sameGroceryHome);$setups->bootstrap($sameRestaurantHome);$homeService->update($sameGroceryHome,['services'=>['deep-home-cleaning'],'setupPreference'=>'SELF','confirmed'=>true]);$homeService->update($sameRestaurantHome,['services'=>['sofa-cleaning'],'setupPreference'=>'ASSISTED','confirmed'=>true]);$assert((int)$pdo->query('SELECT COUNT(*) FROM home_service_provider_profiles WHERE partner_id IN(17,22,23)')->fetchColumn()===3&&$homeService->get($homeContext)['homeService']['services'][0]['code']==='sofa-cleaning','Same-mobile Home Service identity isolation failed');
    $documentService->upload($homeContext,'PAN',$makeUpload($png,'home-pan.png'));$documentService->upload($homeContext,'AADHAAR_FRONT',$makeUpload($png,'home-front.png'));$documentService->upload($homeContext,'AADHAAR_BACK',$makeUpload($png,'home-back.png'));$pdo->exec("UPDATE partner_documents SET review_status='correction_required' WHERE partner_id=17 AND document_type='PAN' AND status='active'");$homeRegressed=$documentService->get($homeContext)['setup'];$assert($homeRegressed['completionPercentage']<100&&$homeRegressed['nextRecommendedStep']==='documents','Earlier correction did not regress completed Home Service readiness');

    $deliveryBankBefore=(int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_bank_accounts')->fetchColumn();$deliveryService=new PartnerDeliveryService($pdo,new PartnerDeliveryRepository($pdo),new PartnerDocumentRepository($pdo),$setups);$emptyDelivery=$deliveryService->get($delivery);$assert($emptyDelivery['delivery']['vehicle']['type']===null&&count($emptyDelivery['options']['vehicleTypes'])===2&&$emptyDelivery['step']['status']==='notStarted','Delivery GET empty state/master options mismatch');
    $throws(fn()=>$deliveryService->update($delivery,['vehicleType'=>'inactive','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'OWNED','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$deliveryService->update($delivery,['vehicleType'=>'motorcycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'OWNED','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$deliveryService->update($delivery,['vehicleType'=>'motorcycle','vehicleRegistrationNumber'=>'bad','vehicleOwnership'=>'OWNED','confirmed'=>true]),'VALIDATION_ERROR');$throws(fn()=>$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'INVALID','confirmed'=>true]),'VALIDATION_ERROR');
    $deliveryAudit=null;$partialDelivery=$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'OWNED','confirmed'=>false],function($meta)use(&$deliveryAudit){$deliveryAudit=$meta;});$assert($partialDelivery['requirements']['registrationRequired']===false&&$partialDelivery['requirements']['drivingLicenceRequired']===false&&$partialDelivery['requirements']['rcRequired']===false&&$partialDelivery['step']['status']==='inProgress','Bicycle dynamic requirements/partial save mismatch');$assert($deliveryAudit['vehicleType']==='bicycle'&&!isset($deliveryAudit['deliveryPartnerId']),'Delivery audit leaked owner ID');
    $bicycleComplete=$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'FAMILY','confirmed'=>true]);$assert($bicycleComplete['setup']['completionPercentage']===100&&$bicycleComplete['remainingRequirements']===[],'Bicycle Delivery Setup did not complete at 6/6');$assert((int)$pdo->query("SELECT COUNT(*) FROM delivery_partner_vehicles WHERE delivery_partner_id=8 AND is_primary=1")->fetchColumn()===1,'Delivery save duplicated current vehicle');
    $motorMissing=$deliveryService->update($delivery,['vehicleType'=>'motorcycle','vehicleRegistrationNumber'=>'up-32 ab 1234','vehicleOwnership'=>'RENTED','confirmed'=>true]);$assert($motorMissing['delivery']['vehicle']['registrationNumber']==='UP32AB1234'&&$motorMissing['requirements']['drivingLicenceRequired']&&$motorMissing['requirements']['rcRequired']&&$motorMissing['step']['status']==='inProgress'&&$motorMissing['setup']['completionPercentage']<100,'Motorcycle requirements/normalization/regression mismatch');$assert(in_array('DRIVING_LICENCE_FRONT',$motorMissing['remainingRequirements'],true)&&in_array('VEHICLE_RC_FRONT',$motorMissing['remainingRequirements'],true),'Missing vehicle documents not reported');
    $throws(fn()=>$documentService->upload($business,'DRIVING_LICENCE_FRONT',$makeUpload($png,'forbidden-dl.png')),'VALIDATION_ERROR');$documentService->upload($delivery,'DRIVING_LICENCE_FRONT',$makeUpload($png,'dl.png'));$documentService->upload($delivery,'VEHICLE_RC_FRONT',$makeUpload($pdf,'rc.pdf'));$motorComplete=$deliveryService->get($delivery);$assert($motorComplete['setup']['completionPercentage']===100&&$motorComplete['step']['status']==='complete','Pending required vehicle documents did not restore Delivery completion');$dlReference=$motorComplete['delivery']['documents'][0]['document']['reference'];$dlFile=$documentService->file($delivery,$dlReference);$assert($dlFile['mime']==='image/png'&&is_file($dlFile['path'])&&!str_contains(json_encode($motorComplete),$documentTestRoot),'Delivery private preview/path safety failed');
    $pdo->exec("UPDATE delivery_partner_documents SET review_status='correction_required' WHERE delivery_partner_id=8 AND document_type='DRIVING_LICENCE_FRONT' AND status='active'");$deliveryCorrection=$deliveryService->get($delivery);$assert($deliveryCorrection['step']['status']==='needsCorrection'&&$deliveryCorrection['setup']['completionPercentage']<100,'Delivery document correction did not regress readiness');$documentService->upload($delivery,'DRIVING_LICENCE_FRONT',$makeUpload($png,'dl-replacement.png'));$assert($deliveryService->get($delivery)['setup']['completionPercentage']===100,'Delivery document replacement did not restore readiness');
    $backToBicycle=$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'COMPANY_PROVIDED','confirmed'=>true]);$assert($backToBicycle['delivery']['documents']===[]&&$backToBicycle['setup']['completionPercentage']===100,'Vehicle change did not recalculate Bicycle requirements');$unconfirmed=$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'OWNED','confirmed'=>false]);$assert($unconfirmed['step']['status']==='inProgress'&&$unconfirmed['setup']['completionPercentage']<100,'confirmed=false did not regress Delivery Setup');
    $finalDelivery=$deliveryService->update($delivery,['vehicleType'=>'bicycle','vehicleRegistrationNumber'=>'','vehicleOwnership'=>'OWNED','confirmed'=>true]);$assert($finalDelivery['setup']['completionPercentage']===100,'Delivery completion was not restorable after confirmation');$pdo->exec("UPDATE delivery_partner_documents SET review_status='correction_required' WHERE delivery_partner_id=8 AND document_type='PAN' AND status='active'");$commonDeliveryCorrection=$documentService->get($delivery);$assert($commonDeliveryCorrection['step']['status']==='needsCorrection'&&$commonDeliveryCorrection['setup']['completionPercentage']<100,'Common Delivery document correction did not regress overall readiness');$documentService->upload($delivery,'PAN',$makeUpload($png,'delivery-pan-replacement.png'));$assert($deliveryService->get($delivery)['setup']['completionPercentage']===100,'Common Delivery document replacement did not restore overall readiness');
    $pdo->exec("INSERT INTO partners(id,user_id,business_name,owner_name,mobile,deleted_at) VALUES(24,20,'Same Mobile Delivery Merchant','Owner','+919222222222',NULL)");$sameMobileDeliveryMerchant=new PartnerContext(20,'USR_delivery_merchant',29,'IDN_delivery_merchant','grocery',1,'RETAIL',24,'PTR_delivery_merchant',null,null,'pending','draft','active','active');$safeDelivery=json_encode($deliveryService->get($delivery));$assert(!preg_match('/deliveryPartnerId|vehicleId|userId|"id"|file_reference|approval_status|availability_status/',$safeDelivery),'Delivery response leaked protected fields');$throws(fn()=>$deliveryService->get($business),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$deliveryService->get($sameMobileDeliveryMerchant),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$deliveryService->get($restaurantSetupContext),'PARTNER_SETUP_NOT_APPLICABLE');$throws(fn()=>$deliveryService->get($homeContext),'PARTNER_SETUP_NOT_APPLICABLE');$assert((int)$pdo->query('SELECT COUNT(*) FROM delivery_partners')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM partners')->fetchColumn()===10,'Delivery Setup crossed same-mobile identity ownership');$assert($pdo->query("SELECT approval_status||':'||availability_status FROM delivery_partners WHERE id=8")->fetchColumn()==='pending:offline','Delivery completion changed approval/online state');$assert((int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_bank_accounts')->fetchColumn()===$deliveryBankBefore,'Delivery Setup changed bank/payout data');

    $reviewService=new PartnerReviewService($pdo,new PartnerReviewRepository($pdo));
    $marketplaceBeforeReview=(int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn();
    $referralBeforeReview=(int)$pdo->query('SELECT COUNT(*) FROM partner_referral_leads')->fetchColumn();
    $bankBeforeReview=(int)$pdo->query('SELECT COUNT(*) FROM partner_bank_accounts')->fetchColumn();
    $deliveryBankBeforeReview=(int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_bank_accounts')->fetchColumn();
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=1");
    $pdo->exec("UPDATE partner_onboarding_applications SET application_status='in_progress',completion_percentage=100,submitted_at=NULL WHERE id=1");
    $businessReview=$reviewService->get($business);
    $assert($businessReview['application']['status']==='READY_TO_SUBMIT'&&$businessReview['application']['canSubmit']&&$businessReview['application']['completionPercentage']===100,'Complete Business review is not ready to submit');
    $assert(count($businessReview['sections'])===8&&$businessReview['sections'][7]['code']==='retail_setup','Business review section composition mismatch');
    $businessReviewJson=json_encode($businessReview);$assert(str_ends_with($businessReview['sections'][6]['data']['maskedAccountNumber'],'9999')&&!str_contains($businessReviewJson,'999999999999')&&!str_contains($businessReviewJson,'account_number_encrypted')&&!str_contains($businessReviewJson,'file_reference'),'Review projection leaked Bank or document internals');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='in_progress' WHERE application_id=1 AND step_code='personal'");
    $throws(fn()=>$reviewService->submit($business,true),'SETUP_INCOMPLETE');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=1 AND step_code='personal'");
    $throws(fn()=>$reviewService->submit($business,false),'VALIDATION_ERROR');
    $submittedReview=$reviewService->submit($business,true);
    $firstSubmittedAt=$submittedReview['application']['submittedAt'];
    $assert($submittedReview['application']['status']==='SUBMITTED'&&!$submittedReview['application']['editable']&&!$submittedReview['application']['canSubmit']&&$firstSubmittedAt!==null,'Business submission lifecycle state mismatch');
    $assert($pdo->query("SELECT onboarding_status FROM partners WHERE id=15")->fetchColumn()==='submitted'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_status_history WHERE application_id=1")->fetchColumn()===1,'Business submission was not synchronized/audited');
    $assert($pdo->query("SELECT application_status FROM partner_onboarding_applications WHERE id=3")->fetchColumn()!=='submitted','Business submission crossed into same-mobile Delivery identity');
    $throws(fn()=>$setups->assertMutable($business,'personal'),'APPLICATION_READ_ONLY');
    $submittedStepCount=(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_steps WHERE application_id=1")->fetchColumn();$submittedBootstrap=$setups->bootstrap($business);$assert($submittedBootstrap['initializedSteps']===0&&$submittedBootstrap['summary']['status']==='submitted'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_steps WHERE application_id=1")->fetchColumn()===$submittedStepCount,'Bootstrap mutated a submitted application');
    $repeatReview=$reviewService->submit($business,true);$assert($repeatReview['application']['submittedAt']===$firstSubmittedAt&&(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_status_history WHERE application_id=1")->fetchColumn()===1,'Repeated submission was not idempotent');
    $pdo->exec("UPDATE partner_onboarding_applications SET application_status='correction_required',completion_percentage=89 WHERE id=1");
    $pdo->exec("UPDATE partners SET onboarding_status='correction_required' WHERE id=15");
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='needs_correction' WHERE application_id=1 AND step_code='documents'");
    $correctionReview=$reviewService->get($business);$assert($correctionReview['application']['status']==='CORRECTION_REQUIRED'&&!$correctionReview['application']['canSubmit']&&$correctionReview['issues'][0]['stepCode']==='documents','Correction review state mismatch');
    $setups->assertMutable($business,'documents');$throws(fn()=>$setups->assertMutable($business,'personal'),'APPLICATION_SECTION_READ_ONLY');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=1 AND step_code='documents'");
    $pdo->exec("UPDATE partner_onboarding_applications SET completion_percentage=100 WHERE id=1");
    $resubmittedReview=$reviewService->submit($business,true);$assert($resubmittedReview['application']['status']==='SUBMITTED'&&(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_status_history WHERE application_id=1")->fetchColumn()===2&&(int)$pdo->query("SELECT COUNT(*) FROM partner_onboarding_applications WHERE business_partner_id=15")->fetchColumn()===1,'Correction resubmission did not reuse the canonical application');
    $pdo->exec("UPDATE partner_onboarding_applications SET application_status='approved' WHERE id=1");$throws(fn()=>$reviewService->submit($business,true),'APPLICATION_READ_ONLY');
    $pdo->exec("UPDATE partner_onboarding_steps SET step_status='complete' WHERE application_id=3");
    $pdo->exec("UPDATE partner_onboarding_applications SET application_status='in_progress',completion_percentage=100,submitted_at=NULL WHERE id=3");
    $deliverySubmitted=$reviewService->submit($delivery,true);$deliveryReviewJson=json_encode($deliverySubmitted);
    $assert($deliverySubmitted['application']['status']==='SUBMITTED'&&count($deliverySubmitted['sections'])===5&&$deliverySubmitted['sections'][4]['code']==='delivery_setup','Delivery review/submission composition mismatch');
    $assert(!str_contains($deliveryReviewJson,'Business Hours')&&!str_contains($deliveryReviewJson,'Operations')&&!str_contains($deliveryReviewJson,'Retail Store Setup'),'Delivery review leaked Business-only sections');
    $assert($pdo->query("SELECT onboarding_status||':'||approval_status||':'||availability_status FROM delivery_partners WHERE id=8")->fetchColumn()==='submitted:pending:offline','Delivery submission changed approval or online state');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM partner_marketplace_settings')->fetchColumn()===$marketplaceBeforeReview&&(int)$pdo->query('SELECT COUNT(*) FROM partner_referral_leads')->fetchColumn()===$referralBeforeReview&&(int)$pdo->query('SELECT COUNT(*) FROM partner_bank_accounts')->fetchColumn()===$bankBeforeReview&&(int)$pdo->query('SELECT COUNT(*) FROM delivery_partner_bank_accounts')->fetchColumn()===$deliveryBankBeforeReview,'Review submission caused an out-of-scope side effect');

    $routes = file_get_contents($root . '/routes/protected.php') . file_get_contents($root . '/routes/public.php');
    foreach (["'/health'", "'/catalogue/parent-categories'", "'/me/context'", "'/me/bootstrap'", "'/referrals/context'", "'/referrals/claim'", "'/referrals/continue-without-referral'", "'/setup'", "'/setup/bootstrap'", "'/setup/personal'", "'/setup/business'", "'/setup/business/description-template'", "'/setup/location'", "'/setup/hours'", "'/setup/operations'", "'/setup/documents'", "'/setup/documents/{publicDocumentId}/file'", "'/setup/bank'", "'/setup/bank/proof'", "'/setup/retail'", "'/setup/restaurant'", "'/setup/home-service'", "'/setup/delivery'", "'/setup/review'", "'/setup/submit'"] as $route) $assert(str_contains($routes, $route), "Missing route $route");
    $assert(!preg_match("#/(onboarding|wallet|orders)#", $routes), 'Out-of-scope route implemented');
    $homeRequestMiddleware=file_get_contents($root.'/Middleware/HomeServiceSetupRequestMiddleware.php');$assert(str_contains($homeRequestMiddleware,"['services','setupPreference','confirmed']")&&!str_contains($homeRequestMiddleware,"'partnerId'")&&!str_contains($homeRequestMiddleware,"'moduleCode'"),'Home Service request allowlist permits protected fields');
    $deliveryRequestMiddleware=file_get_contents($root.'/Middleware/DeliverySetupRequestMiddleware.php');$assert(str_contains($deliveryRequestMiddleware,"['vehicleType','vehicleRegistrationNumber','vehicleOwnership','confirmed']")&&!str_contains($deliveryRequestMiddleware,"'deliveryPartnerId'")&&!str_contains($deliveryRequestMiddleware,"'isOnline'"),'Delivery request allowlist permits protected fields');
    $assert(str_contains($routes, 'RequireIdempotencyKeyMiddleware'), 'Bootstrap idempotency key is not enforced');
    $assert(str_contains($routes,'SetupMutabilityMiddleware::class'),'Central setup mutation guard is not attached');
    $healthController=file_get_contents($root.'/Controllers/PartnerHealthController.php');foreach(['2026.09.14-document-preview-hotfix.3','Env::get','bankEncryptionConfigured','openssl_encrypt','openssl_decrypt']as$token)$assert(str_contains($healthController,$token),"Partner health readiness missing $token");

    $repository = file_get_contents($root . '/Repositories/PartnerContextRepository.php');
    $assert(str_contains($repository, "assertCanonicalTable('partners'"), 'Business canonical schema guard missing');
    $assert(str_contains($repository, "assertCanonicalTable('delivery_partners'"), 'Delivery canonical schema guard missing');
    $assert(str_contains($repository, 'information_schema.statistics'), 'Unique ownership guard missing');
    $assert(str_contains($repository, 'authenticatedMobile'), 'Authenticated mobile resolver missing');

    $referralService = file_get_contents($root . '/Services/PartnerReferralService.php');
    foreach (['PartnerReferralRepository', 'partner_referral_leads', 'allow_mobile_auto_claim', 'REFERRAL_MODULE_MISMATCH', 'REFERRAL_NOT_APPLICABLE'] as $token) {
        $assert(str_contains($referralService, $token), "Referral integration missing $token");
    }
    $assert(!str_contains($referralService, 'reward_amount'), 'Partner referral projection may expose reward amount');
    $assert(!str_contains($referralService, 'referrer_user_id'), 'Partner referral projection may expose referrer identity');
    $assert(!str_contains($referralService, 'secure_token'), 'Partner referral service may expose or persist secure token internals');
    $claimMiddleware = file_get_contents($root . '/Middleware/ReferralClaimRequestMiddleware.php');
    $assert(str_contains($claimMiddleware, "array_keys(\$request->body) !== ['referralToken']"), 'Referral claim input allowlist missing');
    $assert(str_contains($routes, 'ReferralClaimRequestMiddleware'), 'Referral claim request validation missing');
    $assert(str_contains($routes, 'IdempotencyMiddleware'), 'Referral claim idempotency missing');

    $projectRoot = dirname($root, 2);
    $frontendRoot = $projectRoot . '/frontend/src';
    $referralState = file_get_contents($frontendRoot . '/services/referral/referralStateService.js');
    foreach (['sessionStorage', "searchParams.delete('ref')", "searchParams.delete('referralToken')", 'history.replaceState', 'getReferralPrefill'] as $token) {
        $assert(str_contains($referralState, $token), "Frontend referral state missing $token");
    }
    $assert(!str_contains($referralState, 'localStorage'), 'Referral token must not use localStorage');
    $registrationPage=file_get_contents($frontendRoot.'/pages/ReferralRegistrationPage.jsx');
    $referralSelection=file_get_contents($frontendRoot.'/services/referral/referralRegistration.js');
    foreach(['Referred for','hasCapturedToken','resolveCaptured','referralPartnerSelection',"navigate('/login'",'Choose a Partner category']as$token)$assert(str_contains($registrationPage,$token),"Referral registration flow missing $token");
    foreach(['context?.category?.publicId','context?.identityType','referralLocked: true']as$token)$assert(str_contains($referralSelection,$token),"Server-authoritative referral selection missing $token");
    $assert(!str_contains($registrationPage,"searchParams.get('category')")&&!str_contains($registrationPage,"searchParams.get('identityType')"),'Referral registration trusts client category parameters');

    $app = file_get_contents($frontendRoot . '/App.jsx');
    $login = file_get_contents($frontendRoot . '/pages/LoginPage.jsx');
    $dashboard = file_get_contents($frontendRoot . '/pages/DashboardPage.jsx');
    $contextService = file_get_contents($frontendRoot . '/services/partner/partnerContextService.js');
    $guard = file_get_contents($frontendRoot . '/components/ProtectedPartnerRoute.jsx');
    $assert(str_contains($app, 'path="/dashboard"') && str_contains($app, 'ProtectedPartnerRoute'), 'Protected Dashboard route missing');
    $assert(str_contains($contextService, 'PARTNER_CONTEXT_NOT_INITIALIZED') && str_contains($contextService, "post('/me/bootstrap'"), 'Expected first-login bootstrap recovery missing');
    $assert(str_contains($guard, 'getAccessToken') && str_contains($guard, 'ensure()'), 'Dashboard Partner session guard missing');
    $assert(str_contains($guard, "if (context) { setState('ready'); return }") && str_contains($guard, "state === 'retryable'"), 'Partner guard does not preserve cached context/retryable failures');
    $sessionContext=file_get_contents($frontendRoot.'/context/PartnerSessionContext.jsx');
    $assert(str_contains($sessionContext,'pendingEnsure.current'),'Concurrent Partner context resolution is not deduplicated');
    foreach (['Business Setup', 'Complete Setup', 'Today’s Orders', 'Quick actions', 'Setup incomplete'] as $token) {
        $assert(str_contains($dashboard, $token), "Dashboard V1 missing $token");
    }
    foreach (['Authentication Test Result', 'Backend Identity', 'New User:'] as $temporary) {
        $assert(!str_contains($login, $temporary), "Temporary login UI remains: $temporary");
    }
    $assert(str_contains($login, "navigate('/dashboard'"), 'Successful Partner login does not route to Dashboard');
    $assert(str_contains($login,'selectedPartner.referralLocked')&&str_contains($login,'Referred for'),'Referral category can still be changed during login');
    $authService=file_get_contents($frontendRoot.'/services/auth/partnerAuthService.js');
    $assert(str_contains($authService,'selectedPartner.referralIdentityType')&&str_contains($authService,"'fresh-fruits-and-vegetables': 'vegetable'"),'OTP authentication does not preserve the resolved referral identity');

    $setupService = file_get_contents($frontendRoot . '/services/partner/partnerSetupService.js');
    $setupCenter = file_get_contents($frontendRoot . '/pages/SetupCenterPage.jsx');
    foreach (["get('/setup')", "post('/setup/bootstrap'", 'getReferralPrefill'] as $token) {
        $assert(str_contains($setupService, $token), "Frontend setup service missing $token");
    }
    $assert(str_contains($dashboard, 'setup.completionPercentage') && !str_contains($dashboard, 'Math.round(100'), 'Dashboard setup progress is not API-driven');
    foreach (['/setup', '/setup/:step'] as $route) $assert(str_contains($app, $route), "Frontend setup route missing $route");
    foreach (['Complete at your own pace', 'step.title', 'step.routeCode', 'setup.completionPercentage'] as $token) $assert(str_contains($setupCenter, $token), "Setup Center missing $token");
    $reviewPage=file_get_contents($frontendRoot.'/pages/ReviewSubmitPage.jsx');foreach(['Review & Submit','Submit for Review','Resubmit for Review','Submitted for Review','returnTo=review']as$token)$assert(str_contains($reviewPage,$token),"Review & Submit UI missing $token");
    foreach(["get('/setup/review')","post('/setup/submit'",'Idempotency-Key']as$token)$assert(str_contains($setupService,$token),"Review & Submit frontend service missing $token");
    $assert(str_contains($app,'path="/setup/review"')&&str_contains($dashboard,"'/setup/review'")&&str_contains($setupCenter,"'/setup/review'"),'Review & Submit routing/entry points missing');
    $reviewRepository=file_get_contents($root.'/Repositories/PartnerReviewRepository.php');$reviewServiceSource=file_get_contents($root.'/Services/PartnerReviewService.php');foreach(['account_number_encrypted','file_reference','review_note','reviewed_by']as$secret)$assert(!str_contains($reviewServiceSource,$secret),"Review service may expose $secret");foreach(['wallet','ledger','reward','marketplace_visible','approval_status','operational_status']as$boundary)$assert(!str_contains($reviewRepository,$boundary),"Review repository crosses $boundary boundary");
    $phase4gMigration=file_get_contents($projectRoot.'/database/migrations/phase_4g/001_review_submit.sql');foreach(['partner_onboarding_status_history','fk_posh_application','2026_09_01_4g_review_submit']as$token)$assert(str_contains($phase4gMigration,$token),"Phase 4G migration missing $token");
    $businessPage=file_get_contents($frontendRoot.'/pages/BusinessDetailsPage.jsx');
    foreach(['Business Details','Same as verified login mobile','Generate a starter description','Save & Continue'] as $token)$assert(str_contains($businessPage,$token),"Business Details UI missing $token");
    $assert(str_contains($app,'path="/setup/business"')&&str_contains($setupService,"put('/setup/business'"),'Business Details frontend route/service missing');
    $locationPage=file_get_contents($frontendRoot.'/pages/LocationDetailsPage.jsx');
    foreach(['Use My Current Location','Confirm your location on the map','mapConfirmed','Save & Continue']as$token)$assert(str_contains($locationPage,$token),"Location UI missing $token");
    $locationMiddleware=file_get_contents($root.'/Middleware/LocationDetailsRequestMiddleware.php');
    foreach(['partnerId','completionPercentage','serviceabilityStatus']as$protected)$assert(!str_contains($locationMiddleware,"'$protected'"),"Protected Location field was allowlisted: $protected");
    $assert(str_contains($app,'path="/setup/location"')&&str_contains($setupService,"put('/setup/location'"),'Location frontend route/service missing');
    $hoursPage=file_get_contents($frontendRoot.'/pages/BusinessHoursPage.jsx');
    foreach(['Business Hours','Copy Monday to all days','Open 24 Hours','Add Time Slot','Overnight','beforeunload']as$token)$assert(str_contains($hoursPage,$token),"Business Hours UI missing $token");
    $assert(str_contains($app,'path="/setup/hours"')&&str_contains($setupService,"put('/setup/hours'"),'Business Hours frontend route/service missing');
    $hoursMiddleware=file_get_contents($root.'/Middleware/BusinessHoursRequestMiddleware.php');
    foreach(['partnerId','userId','moduleCode','slotOrder','status','completionPercentage']as$protected)$assert(!str_contains($hoursMiddleware,"'$protected'"),"Protected Business Hours field was allowlisted: $protected");
    $operationsPage=file_get_contents($frontendRoot.'/pages/OperationsPage.jsx');
    foreach(['Operations & Fulfilment','Essivery Delivery','Self Delivery','Customer Pickup','Service at Customer Location','beforeunload']as$token)$assert(str_contains($operationsPage,$token),"Operations UI missing $token");
    $assert(str_contains($app,'path="/setup/operations"')&&str_contains($setupService,"put('/setup/operations'"),'Operations frontend route/service missing');
    $operationsRepository=file_get_contents($root.'/Repositories/PartnerFulfilmentRepository.php');
    $assert(!str_contains($operationsRepository,'partner_marketplace_settings')&&!str_contains($operationsRepository,'partner_service_areas'),'Operations repository crosses marketplace/serviceability boundary');
    $documentsPage=file_get_contents($frontendRoot.'/pages/DocumentsPage.jsx');foreach(['Documents & Verification','Aadhaar','PAN Card','getDocumentBlob','URL.createObjectURL','maximum 5 MB']as$token)$assert(str_contains($documentsPage,$token),"Documents UI missing $token");
    $assert(str_contains($app,'path="/setup/documents"')&&str_contains($setupService,"post('/setup/documents'"),'Documents frontend route/service missing');
    $bankPage=file_get_contents($frontendRoot.'/pages/BankDetailsPage.jsx');foreach(['Bank &amp; Payout Details','Account Holder Name','Bank Account Number','Confirm Bank Account Number','IFSC Code','beforeunload','Edit Bank Details','uploadBankProof','Cancelled cheque or passbook','getDocumentBlob','URL.createObjectURL']as$token)$assert(str_contains($bankPage,$token),"Bank UI missing $token");
    $assert(str_contains($app,'path="/setup/bank"')&&str_contains($setupService,"put('/setup/bank'"),'Bank frontend route/service missing');
    $bankContract=file_get_contents($frontendRoot.'/services/partner/bankDetailsContract.js');
    foreach(['accountHolderName','accountNumber','confirmAccountNumber','ifsc','toUpperCase']as$token)$assert(str_contains($bankContract,$token),"Bank serializer missing $token");
    foreach(['maskedAccountNumber','reviewStatus','partnerId','hasBankAccount']as$protected)$assert(!str_contains($bankContract,$protected),"Bank serializer includes display/protected field $protected");
    $assert(str_contains($setupService,'serializeBankDetails(input)'),'Bank request does not use the canonical serializer');
    $bankMiddleware=file_get_contents($root.'/Middleware/BankDetailsRequestMiddleware.php');$assert(str_contains($bankMiddleware,"['accountHolderName','accountNumber','confirmAccountNumber','ifsc']"),'Bank backend allowlist changed');
    $idempotencyRequirement=file_get_contents($root.'/Middleware/RequireIdempotencyKeyMiddleware.php');$emptyBodyRequirement=file_get_contents($root.'/Middleware/EmptyBodyRequestMiddleware.php');
    $assert(!str_contains($idempotencyRequirement,'$request->body'),'Idempotency middleware still rejects valid mutation bodies');
    $assert(str_contains($emptyBodyRequirement,'$request->body !== []')&&str_contains($emptyBodyRequirement,"'/me/bootstrap'")&&str_contains($emptyBodyRequirement,"'/setup/bootstrap'")&&str_contains($emptyBodyRequirement,'!in_array($request->path')&&substr_count($routes,'EmptyBodyRequestMiddleware::class')===2,'Bootstrap empty-body protection is not isolated to both bootstrap routes');
    $bankEncryptionSource=file_get_contents($root.'/Services/PartnerBankEncryption.php');$assert(str_contains($bankEncryptionSource,"Env::get('PARTNER_BANK_ENCRYPTION_KEY')")&&!str_contains($bankEncryptionSource,"getenv('PARTNER_BANK_ENCRYPTION_KEY')"),'Bank encryption bypasses the shared .env loader');
    $documentConfigSource=file_get_contents($root.'/config/documents.php');$assert(str_contains($documentConfigSource,"Env::get('ESSIVERY_PARTNER_DOCUMENT_STORAGE')")&&!str_contains($documentConfigSource,"getenv('ESSIVERY_PARTNER_DOCUMENT_STORAGE')"),'Document storage bypasses the shared .env loader');
    $bankRepository=file_get_contents($root.'/Repositories/PartnerBankAccountRepository.php');$assert(!preg_match('/wallet|ledger|settlement|payout/i',$bankRepository),'Bank repository crosses commercial boundaries');
    $bankController=file_get_contents($root.'/Controllers/PartnerBankController.php');foreach(['last4','ifsc','reviewState']as$safe)$assert(str_contains($bankController,$safe),"Bank audit missing $safe");foreach(['accountNumber','confirmAccountNumber','account_number_encrypted']as$secret)$assert(!str_contains($bankController,$secret),"Bank audit may expose $secret");
    $retailPage=file_get_contents($frontendRoot.'/pages/RetailSetupPage.jsx');foreach(['Retail Store Setup','My Store Category',"I'll add products myself",'I need Essivery assistance','Product catalogue setup comes next','beforeunload']as$token)$assert(str_contains($retailPage,$token),"Retail UI missing $token");$assert(str_contains($app,'path="/setup/retail"')&&str_contains($setupService,"put('/setup/retail'"),'Retail frontend route/service missing');
    $retailMiddleware=file_get_contents($root.'/Middleware/RetailSetupRequestMiddleware.php');foreach(['parentCategoryId','identityType','moduleCode','approvalStatus','marketplaceStatus','isLive']as$protected)$assert(!str_contains($retailMiddleware,"'$protected'"),"Protected Retail field was allowlisted: $protected");
    $retailRepository=file_get_contents($root.'/Repositories/PartnerRetailRepository.php');foreach(['master_products','inventory','partner_marketplace_settings','grocery_stores']as$boundary)$assert(!str_contains($retailRepository,$boundary),"Retail repository crosses $boundary boundary");
    $restaurantPage=file_get_contents($frontendRoot.'/pages/RestaurantSetupPage.jsx');foreach(['Restaurant Setup','Select cuisines','Search cuisines',"I'll add my menu myself",'I need Essivery assistance','beforeunload']as$token)$assert(str_contains($restaurantPage,$token),"Restaurant UI missing $token");$assert(str_contains($app,'path="/setup/restaurant"')&&str_contains($setupService,"put('/setup/restaurant'"),'Restaurant frontend route/service missing');$restaurantRepo=file_get_contents($root.'/Repositories/PartnerRestaurantRepository.php');foreach(['menu_items','inventory','partner_marketplace_settings','orders']as$boundary)$assert(!str_contains($restaurantRepo,$boundary),"Restaurant repository crosses $boundary boundary");
    $documentController=file_get_contents($root.'/Controllers/PartnerDocumentsController.php');foreach(['X-Content-Type-Options: nosniff','Cache-Control: private, no-store','Content-Disposition: inline']as$header)$assert(str_contains($documentController,$header),"Private document response missing $header");$assert(str_contains($documentController,"'uploaded'=>true")&&!str_contains($documentController,'bankService()'),'Bank proof upload can report a post-commit Bank refresh failure');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentTestRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as$item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}if(is_dir($documentTestRoot))rmdir($documentTestRoot);

    $setupMigration = file_get_contents($projectRoot . '/database/migrations/phase_3a/001_partner_setup_persistence.sql');
    foreach (['partner_onboarding_applications', 'partner_onboarding_steps', 'uq_partner_setup_business_current', 'uq_partner_setup_delivery_current', 'uq_partner_setup_step', 'fk_partner_setup_business', 'fk_partner_setup_delivery'] as $token) {
        $assert(str_contains($setupMigration, $token), "Prompt 3A migration missing $token");
    }
    foreach (['business_name', 'address_line', 'bank_account', 'documents_json'] as $duplicateField) {
        $assert(!str_contains($setupMigration, $duplicateField), "Setup persistence duplicates authoritative business data: $duplicateField");
    }

    $migration = file_get_contents($projectRoot . '/database/migrations/phase_2a/001_reconcile_partner_core.sql');
    $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS partners'), 'Partner core reconciliation migration missing');
    $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS delivery_partners'), 'Delivery core reconciliation migration missing');
    $assert(substr_count($migration, 'UNIQUE KEY uq_partners_user (user_id)') === 1, 'Business ownership uniqueness missing');
    $assert(substr_count($migration, 'UNIQUE KEY uq_delivery_partners_user (user_id)') === 1, 'Delivery ownership uniqueness missing');
    $assert(!preg_match('/(?:^|\R)\s*(?:INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER)\b/i', $migration), 'Reconciliation migration must remain additive and row-free');

    $index = file_get_contents($root . '/index.php');
    $assert(str_contains($index, 'https://partners.essivery.in'), 'Production Partner CORS origin missing');
    $assert(!str_contains($index, 'https://partner' . '.essivery.in'), 'Legacy singular Partner CORS origin must not remain');
    $assert(str_contains($index, 'http://localhost:5173'), 'Development Partner CORS origin missing');
    $health = file_get_contents($root . '/Controllers/PartnerHealthController.php');
    foreach (['database', 'schema', 'phpversion', 'hostname'] as $leak) $assert(!str_contains(strtolower($health), $leak), "Health may leak $leak");

    echo "Partner foundation tests passed: $tests assertions\n";
}
