<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Api\Services\AuditService;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerLocationRepository;
use Essivery\Partner\Services\PartnerLocationService;
use Essivery\Partner\Services\PartnerSetupService;

final class PartnerLocationController
{
    public function __construct(private array$container){}
    public function show(Request$r):never{Response::success($this->service()->get($this->context($r)),200,['requestId'=>$r->requestId]);}
    public function update(Request$r):never{$auth=Auth::context($r);$audit=new AuditService($this->pdo());$safe=array_values(array_intersect(array_keys($r->body),['addressLine1','addressLine2','locality','landmark','pincode','city','state','latitude','longitude','mapConfirmed']));$result=$this->service()->update($this->context($r),$r->body,function()use($audit,$auth,$r,$safe):void{$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.location_updated','partner_setup_application',null,$r->requestId,['fields'=>$safe]);$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.location_completed','partner_setup_application',null,$r->requestId);});Response::success($result,200,['requestId'=>$r->requestId]);}
    private function service():PartnerLocationService{$pdo=$this->pdo();$definitions=$this->container['partner']['setup']??require dirname(__DIR__).'/config/setup.php';return new PartnerLocationService($pdo,new PartnerLocationRepository($pdo),new PartnerSetupService($pdo,$definitions));}
    private function context(Request$r):PartnerContext{return$r->attributes['partnerContext'];}
    private function pdo():\PDO{return$this->container['database']->connection();}
}
