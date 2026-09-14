<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Api\Services\AuditService;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerDocumentRepository;
use Essivery\Partner\Services\PartnerDocumentStorage;
use Essivery\Partner\Services\PartnerDocumentsService;
use Essivery\Partner\Services\PartnerSetupService;

final class PartnerDocumentsController
{
    public function __construct(private array$container){}
    public function show(Request$r):never{Response::success($this->service()->get($this->context($r)),200,['requestId'=>$r->requestId]);}
    public function upload(Request$r):never
    {
        $auth=Auth::context($r);$audit=new AuditService($this->pdo());$state=$this->service()->upload($this->context($r),(string)($r->body['documentType']??''),$r->files['file'],function(array$m)use($audit,$auth,$r):void{$event=$m['replaced']?'partner_setup.document_replaced':'partner_setup.document_uploaded';$safe=['documentType'=>$m['documentType'],'mimeFamily'=>$m['mimeFamily'],'reviewState'=>$m['reviewState']];$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],$event,'partner_setup_application',null,$r->requestId,$safe);if($m['correctionResolved'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.document_correction_resolved','partner_setup_application',null,$r->requestId,$safe);if($m['completed'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.documents_completed','partner_setup_application',null,$r->requestId);});Response::success($state,200,['requestId'=>$r->requestId]);
    }
    public function uploadBankProof(Request$r):never
    {
        $auth=Auth::context($r);$audit=new AuditService($this->pdo());$this->service()->upload($this->context($r),'BANK_PROOF',$r->files['file'],function(array$m)use($audit,$auth,$r):void{$event=$m['replaced']?'partner_setup.bank_proof_replaced':'partner_setup.bank_proof_uploaded';$safe=['documentType'=>'BANK_PROOF','mimeFamily'=>$m['mimeFamily'],'reviewState'=>$m['reviewState']];$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],$event,'partner_setup_application',null,$r->requestId,$safe);if($m['correctionResolved'])$audit->record((int)$auth['user_id'],(int)$auth['identity_id'],'partner_setup.bank_proof_correction_resolved','partner_setup_application',null,$r->requestId,$safe);});Response::success(['uploaded'=>true,'documentType'=>'BANK_PROOF','reviewStatus'=>'pending'],200,['requestId'=>$r->requestId]);
    }
    public function file(Request$r):never
    {
        $value=$this->service()->file($this->context($r),(string)($r->routeParams['publicDocumentId']??''));header('Content-Type: '.$value['mime']);header('Content-Length: '.filesize($value['path']));header('Content-Disposition: inline; filename="document.'.$value['extension'].'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');readfile($value['path']);exit;
    }
    private function service():PartnerDocumentsService{$pdo=$this->pdo();$definitions=$this->container['partner']['setup']??require dirname(__DIR__).'/config/setup.php';$config=$this->container['partner']['documents']??require dirname(__DIR__).'/config/documents.php';return new PartnerDocumentsService($pdo,new PartnerDocumentRepository($pdo),new PartnerDocumentStorage($config),new PartnerSetupService($pdo,$definitions));}
    private function context(Request$r):PartnerContext{return$r->attributes['partnerContext'];}
    private function pdo():\PDO{return$this->container['database']->connection();}
}
