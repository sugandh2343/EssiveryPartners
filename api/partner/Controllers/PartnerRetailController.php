<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;use Essivery\Api\Core\Request;use Essivery\Api\Core\Response;use Essivery\Api\Services\AuditService;use Essivery\Partner\Domain\PartnerContext;use Essivery\Partner\Repositories\PartnerRetailRepository;use Essivery\Partner\Services\PartnerRetailService;use Essivery\Partner\Services\PartnerSetupService;

final class PartnerRetailController
{
    public function __construct(private array$container){}
    public function show(Request$r):never{Response::success($this->service()->get($this->context($r)),200,['requestId'=>$r->requestId]);}
    public function update(Request$r):never{$auth=Auth::context($r);$audit=new AuditService($this->pdo());$result=$this->service()->update($this->context($r),$r->body,function(array$m)use($audit,$auth,$r):void{$safe=['categoryCode'=>$m['categoryCode'],'catalogueSetupMode'=>$m['catalogueSetupMode']];$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.retail_updated','partner_setup_application',null,$r->requestId,$safe);$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.retail_completed','partner_setup_application',null,$r->requestId,$safe);if($m['modeChanged'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.retail_catalogue_mode_changed','partner_setup_application',null,$r->requestId,$safe);});Response::success($result,200,['requestId'=>$r->requestId]);}
    private function service():PartnerRetailService{$pdo=$this->pdo();$definitions=$this->container['partner']['setup']??require dirname(__DIR__).'/config/setup.php';return new PartnerRetailService($pdo,new PartnerRetailRepository($pdo),new PartnerSetupService($pdo,$definitions));}
    private function context(Request$r):PartnerContext{return$r->attributes['partnerContext'];}
    private function pdo():\PDO{return$this->container['database']->connection();}
}
