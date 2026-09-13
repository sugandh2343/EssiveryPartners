<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerDocumentRepository
{
    private bool$schemaChecked=false;
    public function __construct(private PDO$pdo){}
    public function current(PartnerContext$c):array{$this->assertSchema();[$table,$owner,$value]=$this->owner($c);$q=$this->pdo->prepare("SELECT public_id,document_type,file_reference,review_status,status,created_at FROM $table WHERE $owner=:owner AND status='active' ORDER BY id");$q->execute(['owner'=>$value]);return$q->fetchAll();}
    public function find(PartnerContext$c,string$public):array{$this->assertSchema();[$table,$owner,$value]=$this->owner($c);$q=$this->pdo->prepare("SELECT public_id,document_type,file_reference,review_status,status,created_at FROM $table WHERE public_id=:public AND $owner=:owner AND status='active' LIMIT 1");$q->execute(['public'=>$public,'owner'=>$value]);return$q->fetch()?:[];}
    public function replace(PartnerContext$c,string$type,string$reference):array
    {
        [$table,$owner,$value]=$this->owner($c);$q=$this->pdo->prepare("SELECT public_id,file_reference,review_status FROM $table WHERE $owner=:owner AND document_type=:type AND status='active' LIMIT 1".($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'));$q->execute(['owner'=>$value,'type'=>$type]);$old=$q->fetch()?:[];
        if($old){$retire=$this->pdo->prepare("UPDATE $table SET status='inactive' WHERE public_id=:public AND $owner=:owner");$retire->execute(['public'=>$old['public_id'],'owner'=>$value]);}
        $insert=$this->pdo->prepare("INSERT INTO $table(public_id,$owner,document_type,file_reference,review_status,status) VALUES(:public,:owner,:type,:reference,'pending','active')");$insert->execute(['public'=>Helpers::publicId('DOC'),'owner'=>$value,'type'=>$type,'reference'=>$reference]);return['replaced'=>(bool)$old,'oldReference'=>$old['file_reference']??null,'correctionResolved'=>in_array($old['review_status']??'', ['rejected','correction_required','needs_correction'],true)];
    }
    private function owner(PartnerContext$c):array{return$c->deliveryPartnerId!==null?['delivery_partner_documents','delivery_partner_id',$c->deliveryPartnerId]:['partner_documents','partner_id',$c->businessPartnerId];}
    private function assertSchema():void
    {
        if($this->schemaChecked||$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$this->schemaChecked=true;return;}$missing=[];foreach(['partner_documents','delivery_partner_documents']as$table){$q=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);$actual=array_column($q->fetchAll(),'column_name');foreach(array_diff(['public_id','document_type','active_document_type','file_reference','review_status','status'], $actual)as$column)$missing[]="$table.$column";}if($missing)throw new ApiException(503,'PARTNER_DOCUMENT_SCHEMA_UNAVAILABLE','The Phase 4A document schema is incomplete.','Documents are temporarily unavailable.',[],true,['missingColumns'=>$missing]);$this->schemaChecked=true;
    }
}
