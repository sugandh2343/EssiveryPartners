<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;use Essivery\Api\Core\Helpers;use Essivery\Partner\Domain\PartnerContext;use PDO;

final class PartnerRetailRepository
{
    private bool$schemaChecked=false;
    public function __construct(private PDO$pdo){}
    public function context(PartnerContext$c):array
    {
        $this->assertSchema();$q=$this->pdo->prepare("SELECT p.business_name,c.slug category_slug,c.name category_name,c.icon_url,m.catalogue_setup_mode,m.retail_confirmed_at FROM partners p JOIN parent_categories c ON c.id=:category LEFT JOIN partner_retail_parent_categories m ON m.partner_id=p.id AND m.parent_category_id=c.id AND m.status='active' WHERE p.id=:partner AND p.user_id=:user AND p.deleted_at IS NULL AND c.status='active' AND c.deleted_at IS NULL LIMIT 1");$q->execute(['category'=>$c->parentCategoryId,'partner'=>$c->businessPartnerId,'user'=>$c->authenticatedUserId]);return$q->fetch()?:[];
    }
    public function save(PartnerContext$c,string$mode):array
    {
        $this->ensureModule($c);$params=['public'=>Helpers::publicId('PRC'),'partner'=>$c->businessPartnerId,'category'=>$c->parentCategoryId,'mode'=>$mode];$sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ?"INSERT INTO partner_retail_parent_categories(public_id,partner_id,parent_category_id,catalogue_setup_mode,retail_confirmed_at,status) VALUES(:public,:partner,:category,:mode,CURRENT_TIMESTAMP,'active') ON CONFLICT(partner_id,parent_category_id) DO UPDATE SET catalogue_setup_mode=excluded.catalogue_setup_mode,retail_confirmed_at=CURRENT_TIMESTAMP,status='active',updated_at=CURRENT_TIMESTAMP"
            :"INSERT INTO partner_retail_parent_categories(public_id,partner_id,parent_category_id,catalogue_setup_mode,retail_confirmed_at,status) VALUES(:public,:partner,:category,:mode,CURRENT_TIMESTAMP(6),'active') ON DUPLICATE KEY UPDATE catalogue_setup_mode=VALUES(catalogue_setup_mode),retail_confirmed_at=CURRENT_TIMESTAMP(6),status='active',updated_at=CURRENT_TIMESTAMP(6)";
        $this->pdo->prepare($sql)->execute($params);return$this->context($c);
    }
    private function ensureModule(PartnerContext$c):void
    {
        $params=['public'=>Helpers::publicId('PBM'),'partner'=>$c->businessPartnerId,'module'=>'RETAIL'];$sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ?"INSERT INTO partner_business_modules(public_id,partner_id,module_code,status) VALUES(:public,:partner,:module,'active') ON CONFLICT(partner_id,module_code) DO UPDATE SET status='active',updated_at=CURRENT_TIMESTAMP"
            :"INSERT INTO partner_business_modules(public_id,partner_id,module_code,status) VALUES(:public,:partner,:module,'active') ON DUPLICATE KEY UPDATE status='active',updated_at=CURRENT_TIMESTAMP(6)";$this->pdo->prepare($sql)->execute($params);
    }
    private function assertSchema():void
    {
        if($this->schemaChecked||$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$this->schemaChecked=true;return;}$required=['partner_retail_parent_categories'=>['public_id','partner_id','parent_category_id','catalogue_setup_mode','retail_confirmed_at','status','updated_at'],'partner_business_modules'=>['public_id','partner_id','module_code','status','updated_at'],'parent_categories'=>['id','slug','name','icon_url','status','deleted_at']];$missing=[];foreach($required as$table=>$columns){$q=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);foreach(array_diff($columns,array_column($q->fetchAll(),'column_name'))as$column)$missing[]="$table.$column";}if($missing)throw new ApiException(503,'PARTNER_RETAIL_SCHEMA_UNAVAILABLE','The Phase 4C Retail schema is incomplete.','Retail Setup is temporarily unavailable.',[],true,['missingColumns'=>$missing]);$this->schemaChecked=true;
    }
}
