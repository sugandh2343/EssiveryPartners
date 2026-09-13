<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerDocumentRepository;
use PDO;
use Throwable;

final class PartnerDocumentsService
{
    private const REQUIREMENTS=['AADHAAR_FRONT'=>'Aadhaar Card - Front','AADHAAR_BACK'=>'Aadhaar Card - Back','PAN'=>'PAN Card'];
    private const DELIVERY_DOCUMENTS=['DRIVING_LICENCE_FRONT','VEHICLE_RC_FRONT','VEHICLE_INSURANCE'];
    private const SUPPLEMENTAL_DOCUMENTS=['BANK_PROOF'];
    public function __construct(private PDO$pdo,private PartnerDocumentRepository$documents,private PartnerDocumentStorage$storage,private PartnerSetupService$setup){}
    public function get(PartnerContext$c):array{return$this->state($c,true);}
    public function upload(PartnerContext$c,string$type,array$file,?callable$beforeCommit=null):array
    {
        $type=strtoupper(trim($type));$common=isset(self::REQUIREMENTS[$type]);$deliverySpecific=$c->deliveryPartnerId!==null&&in_array($type,self::DELIVERY_DOCUMENTS,true);$supplemental=in_array($type,self::SUPPLEMENTAL_DOCUMENTS,true);if(!$common&&!$deliverySpecific&&!$supplemental)throw new ApiException(422,'VALIDATION_ERROR','Unknown document type.','Choose a document required for this Partner identity.',['documentType'=>'Unsupported document type.']);
        $ownerKind=$c->deliveryPartnerId!==null?'delivery-partners':'partners';$ownerPublic=$c->deliveryPartnerPublicId??$c->businessPartnerPublicId;if(!$ownerPublic)throw new ApiException(409,'PARTNER_CONTEXT_AMBIGUOUS','Document owner could not be resolved.','Your Partner profile could not be resolved safely.');
        $stored=$this->storage->store($file,$ownerKind,$ownerPublic);$this->pdo->beginTransaction();try{$replacement=$this->documents->replace($c,$type,$stored['reference']);$state=$this->state($c,$type!=='BANK_PROOF');if($beforeCommit)$beforeCommit(['documentType'=>$type,'mimeFamily'=>$stored['family'],'reviewState'=>'pending','replaced'=>$replacement['replaced'],'correctionResolved'=>$replacement['correctionResolved'],'completed'=>$type!=='BANK_PROOF'&&$state['step']['status']==='complete']);$this->pdo->commit();return$state;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->storage->remove($stored['reference']);throw$e;}
    }
    public function file(PartnerContext$c,string$public):array
    {
        if(!preg_match('/^(?:DOC_[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})$/i',$public))throw new ApiException(404,'DOCUMENT_NOT_FOUND','Document reference is invalid.','Document was not found.');$row=$this->documents->find($c,$public);if(!$row)throw new ApiException(404,'DOCUMENT_NOT_FOUND','Owned document was not found.','Document was not found.');$path=$this->storage->path((string)$row['file_reference']);if(!is_file($path))throw new ApiException(404,'DOCUMENT_FILE_NOT_FOUND','Document file is missing from private storage.','Document file was not found.');$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';$allowed=['image/jpeg','image/png','image/webp','application/pdf'];if(!in_array($mime,$allowed,true))throw new ApiException(409,'DOCUMENT_FILE_INVALID','Stored document MIME is not supported.','Document preview is unavailable.');return['path'=>$path,'mime'=>$mime,'extension'=>$mime==='application/pdf'?'pdf':($mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg'))];
    }
    private function state(PartnerContext$c,bool$sync):array
    {
        $byType=[];foreach($this->documents->current($c)as$row)$byType[strtoupper((string)$row['document_type'])]=$row;$requirements=[];$resolved=0;$correction=false;foreach(self::REQUIREMENTS as$code=>$label){$row=$byType[$code]??null;$review=$row?$this->review((string)$row['review_status']):null;$counts=$row&&in_array($review,['pending','approved'],true);if($counts)$resolved++;if($row&&in_array($review,['needs_correction','rejected'],true))$correction=true;$requirements[]=['code'=>$code,'label'=>$label,'required'=>true,'uploaded'=>(bool)$row,'reviewStatus'=>$review,'countsTowardCompletion'=>(bool)$counts,'document'=>$row?['reference'=>$row['public_id'],'fileType'=>$this->fileType((string)$row['file_reference']),'mimeCategory'=>$this->fileType((string)$row['file_reference']),'uploadedAt'=>$row['created_at']?gmdate('c',strtotime((string)$row['created_at'])):null,'viewUrl'=>'/setup/documents/'.$row['public_id'].'/file']:null];}$complete=$resolved===count(self::REQUIREMENTS);$setup=$sync?$this->setup->syncDocumentsState($c,$complete,$correction):$this->setup->load($c);return['requirements'=>$requirements,'uploadedCount'=>$resolved,'requiredCount'=>count(self::REQUIREMENTS),'step'=>$this->step($setup),'setup'=>$setup];
    }
    private function review(string$status):string{return match(strtolower($status)){'approved'=>'approved','rejected'=>'rejected','correction_required','needs_correction'=>'needs_correction',default=>'pending'};}
    private function fileType(string$reference):string{return str_ends_with(strtolower($reference),'.pdf')?'pdf':'image';}
    private function step(array$setup):array{return array_values(array_filter($setup['steps'],fn(array$s):bool=>$s['code']==='documents'))[0]??['code'=>'documents','status'=>'notStarted'];}
}
