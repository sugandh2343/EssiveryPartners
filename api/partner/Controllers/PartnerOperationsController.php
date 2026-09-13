<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Api\Services\AuditService;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerFulfilmentRepository;
use Essivery\Partner\Services\PartnerOperationsService;
use Essivery\Partner\Services\PartnerSetupService;

final class PartnerOperationsController
{
    public function __construct(private array $container) {}
    public function show(Request $request): never { Response::success($this->service()->get($this->context($request)), 200, ['requestId'=>$request->requestId]); }
    public function update(Request $request): never
    {
        $auth=Auth::context($request);$audit=new AuditService($this->pdo());
        $result=$this->service()->update($this->context($request),$request->body,function(array $metadata)use($audit,$auth,$request):void{$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.operations_updated','partner_setup_application',null,$request->requestId,$metadata);$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.operations_completed','partner_setup_application',null,$request->requestId);});
        Response::success($result,200,['requestId'=>$request->requestId]);
    }
    private function service():PartnerOperationsService{$pdo=$this->pdo();$definitions=$this->container['partner']['setup']??require dirname(__DIR__).'/config/setup.php';return new PartnerOperationsService($pdo,new PartnerFulfilmentRepository($pdo),new PartnerSetupService($pdo,$definitions));}
    private function context(Request$request):PartnerContext{return$request->attributes['partnerContext'];}
    private function pdo():\PDO{return$this->container['database']->connection();}
}
