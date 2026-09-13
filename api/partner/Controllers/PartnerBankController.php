<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;use Essivery\Api\Core\Request;use Essivery\Api\Core\Response;use Essivery\Api\Services\AuditService;use Essivery\Partner\Domain\PartnerContext;use Essivery\Partner\Repositories\PartnerBankAccountRepository;use Essivery\Partner\Repositories\PartnerDocumentRepository;use Essivery\Partner\Services\PartnerBankEncryption;use Essivery\Partner\Services\PartnerBankService;use Essivery\Partner\Services\PartnerSetupService;

final class PartnerBankController
{
    public function __construct(private array$container){}
    public function show(Request$r):never{Response::success($this->service()->get($this->context($r)),200,['requestId'=>$r->requestId]);}
    public function update(Request$r):never{$auth=Auth::context($r);$audit=new AuditService($this->pdo());$result=$this->service()->update($this->context($r),$r->body,function(array$m)use($audit,$auth,$r):void{$safe=['last4'=>$m['last4'],'ifsc'=>$m['ifsc'],'reviewState'=>$m['reviewState']];$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.bank_updated','partner_setup_application',null,$r->requestId,$safe);if($m['completed'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.bank_completed','partner_setup_application',null,$r->requestId,$safe);if($m['changed'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.bank_account_changed','partner_setup_application',null,$r->requestId,$safe);});Response::success($result,200,['requestId'=>$r->requestId]);}
    private function service():PartnerBankService{$pdo=$this->pdo();$definitions=$this->container['partner']['setup']??require dirname(__DIR__).'/config/setup.php';return new PartnerBankService($pdo,new PartnerBankAccountRepository($pdo),new PartnerDocumentRepository($pdo),new PartnerBankEncryption(),new PartnerSetupService($pdo,$definitions));}
    private function context(Request$r):PartnerContext{return$r->attributes['partnerContext'];}
    private function pdo():\PDO{return$this->container['database']->connection();}
}
