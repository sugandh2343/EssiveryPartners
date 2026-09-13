<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerHomeServiceRepository
{
    private bool $checked = false;
    public function __construct(private PDO $pdo) {}

    public function options(): array
    {
        $this->schema();
        return $this->pdo->query("SELECT m.id,m.slug,m.name,c.slug category_code,c.name category_name,s.slug subcategory_code,s.name subcategory_name
            FROM home_service_masters m
            JOIN home_service_categories c ON c.id=m.category_id AND c.status='active'
            LEFT JOIN home_service_subcategories s ON s.id=m.subcategory_id
            WHERE m.status='active' AND (s.id IS NULL OR s.status='active')
            ORDER BY c.display_order,c.name,COALESCE(s.display_order,0),COALESCE(s.name,''),m.display_order,m.name")->fetchAll();
    }

    public function profile(PartnerContext $context): array
    {
        $this->schema();
        $q=$this->pdo->prepare("SELECT p.business_name,h.id provider_id,h.setup_preference,h.setup_confirmed_at
            FROM partners p LEFT JOIN home_service_provider_profiles h ON h.partner_id=p.id
            WHERE p.id=:partner AND p.user_id=:user AND p.deleted_at IS NULL LIMIT 1");
        $q->execute(['partner'=>$context->businessPartnerId,'user'=>$context->authenticatedUserId]);
        $row=$q->fetch()?:[];$row['services']=[];
        if(!empty($row['provider_id'])){
            $q=$this->pdo->prepare("SELECT m.slug,m.name FROM home_service_provider_setup_services x JOIN home_service_masters m ON m.id=x.service_id WHERE x.provider_id=:provider ORDER BY m.name");
            $q->execute(['provider'=>$row['provider_id']]);$row['services']=$q->fetchAll();
        }
        return $row;
    }

    public function resolveServices(array $codes): array
    {
        $this->schema();$ph=implode(',',array_fill(0,count($codes),'?'));
        $q=$this->pdo->prepare("SELECT m.id,m.slug,m.name FROM home_service_masters m JOIN home_service_categories c ON c.id=m.category_id AND c.status='active' LEFT JOIN home_service_subcategories s ON s.id=m.subcategory_id WHERE m.status='active' AND (s.id IS NULL OR s.status='active') AND m.slug IN($ph)");
        $q->execute($codes);return$q->fetchAll();
    }

    public function save(PartnerContext $context,string $preference,array $services): void
    {
        $this->ensureModule($context);$params=['public'=>Helpers::publicId('HSP'),'partner'=>$context->businessPartnerId,'preference'=>$preference];
        $sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ?"INSERT INTO home_service_provider_profiles(public_id,partner_id,setup_preference,setup_confirmed_at) VALUES(:public,:partner,:preference,CURRENT_TIMESTAMP) ON CONFLICT(partner_id) DO UPDATE SET setup_preference=excluded.setup_preference,setup_confirmed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP"
            :"INSERT INTO home_service_provider_profiles(public_id,partner_id,setup_preference,setup_confirmed_at) VALUES(:public,:partner,:preference,CURRENT_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE setup_preference=VALUES(setup_preference),setup_confirmed_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6)";
        $this->pdo->prepare($sql)->execute($params);
        $q=$this->pdo->prepare('SELECT id FROM home_service_provider_profiles WHERE partner_id=:partner LIMIT 1');$q->execute(['partner'=>$context->businessPartnerId]);$provider=(int)$q->fetchColumn();
        $this->pdo->prepare('DELETE FROM home_service_provider_setup_services WHERE provider_id=:provider')->execute(['provider'=>$provider]);
        $insert=$this->pdo->prepare('INSERT INTO home_service_provider_setup_services(provider_id,service_id) VALUES(:provider,:service)');
        foreach($services as$service)$insert->execute(['provider'=>$provider,'service'=>$service['id']]);
    }

    private function ensureModule(PartnerContext $context): void
    {
        $params=['public'=>Helpers::publicId('PBM'),'partner'=>$context->businessPartnerId,'module'=>'HOME_SERVICE'];
        $sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ?"INSERT INTO partner_business_modules(public_id,partner_id,module_code,status) VALUES(:public,:partner,:module,'active') ON CONFLICT(partner_id,module_code) DO UPDATE SET status='active',updated_at=CURRENT_TIMESTAMP"
            :"INSERT INTO partner_business_modules(public_id,partner_id,module_code,status) VALUES(:public,:partner,:module,'active') ON DUPLICATE KEY UPDATE status='active',updated_at=CURRENT_TIMESTAMP(6)";
        $this->pdo->prepare($sql)->execute($params);
    }

    private function schema(): void
    {
        if($this->checked||$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$this->checked=true;return;}
        $required=[
            'home_service_categories'=>['id','slug','name','display_order','status'],
            'home_service_subcategories'=>['id','slug','name','display_order','status'],
            'home_service_masters'=>['id','category_id','subcategory_id','slug','name','display_order','status'],
            'home_service_provider_profiles'=>['id','public_id','partner_id','setup_preference','setup_confirmed_at','marketplace_visible','status'],
            'home_service_provider_setup_services'=>['provider_id','service_id'],
        ];$missing=[];
        foreach($required as$table=>$columns){$q=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);foreach(array_diff($columns,array_column($q->fetchAll(),'column_name'))as$column)$missing[]="$table.$column";}
        if($missing)throw new ApiException(503,'PARTNER_HOME_SERVICE_SCHEMA_UNAVAILABLE','The Phase 4E Home Service schema is incomplete.','Home Service Setup is temporarily unavailable.',[],true,['missingColumns'=>$missing]);$this->checked=true;
    }
}

