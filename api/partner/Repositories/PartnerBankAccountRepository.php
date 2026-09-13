<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerBankAccountRepository
{
    private bool$schemaChecked=false;
    public function __construct(private PDO$pdo){}
    public function current(PartnerContext$c,bool$lock=false):array
    {
        $this->assertSchema();[$table,$owner,$value]=$this->owner($c);$suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'?' FOR UPDATE':'';
        $q=$this->pdo->prepare("SELECT public_id,account_holder_name,account_number_encrypted,account_number_last4,encryption_version,ifsc_code,review_status,review_note,status,updated_at FROM $table WHERE $owner=:owner AND status='active' LIMIT 1$suffix");$q->execute(['owner'=>$value]);return$q->fetch()?:[];
    }
    public function replace(PartnerContext$c,array$v):array
    {
        [$table,$owner,$value]=$this->owner($c);$old=$this->current($c,true);
        if($old){$q=$this->pdo->prepare("UPDATE $table SET status='inactive' WHERE $owner=:owner AND status='active'");$q->execute(['owner'=>$value]);}
        $q=$this->pdo->prepare("INSERT INTO $table(public_id,$owner,account_holder_name,account_number_encrypted,account_number_last4,encryption_version,ifsc_code,review_status,status) VALUES(:public,:owner,:holder,:encrypted,:last4,:version,:ifsc,'pending','active')");
        $q->bindValue(':public',Helpers::publicId('BNK'));$q->bindValue(':owner',$value,PDO::PARAM_INT);$q->bindValue(':holder',$v['holder']);$q->bindValue(':encrypted',$v['encrypted'],PDO::PARAM_LOB);$q->bindValue(':last4',$v['last4']);$q->bindValue(':version',$v['version'],PDO::PARAM_INT);$q->bindValue(':ifsc',$v['ifsc']);$q->execute();return['previous'=>(bool)$old];
    }
    private function owner(PartnerContext$c):array{return$c->deliveryPartnerId!==null?['delivery_partner_bank_accounts','delivery_partner_id',$c->deliveryPartnerId]:['partner_bank_accounts','partner_id',$c->businessPartnerId];}
    private function assertSchema():void
    {
        if($this->schemaChecked||$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$this->schemaChecked=true;return;}$missing=[];
        foreach(['partner_bank_accounts'=>['partner_id'],'delivery_partner_bank_accounts'=>['delivery_partner_id']]as$table=>$owners){$q=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);$actual=array_column($q->fetchAll(),'column_name');foreach(array_diff(array_merge($owners,['public_id','account_holder_name','account_number_encrypted','account_number_last4','encryption_version','ifsc_code','review_status','status']),$actual)as$column)$missing[]="$table.$column";}
        if($missing)throw new ApiException(503,'PARTNER_BANK_SCHEMA_UNAVAILABLE','The Phase 4B Bank schema is incomplete.','Bank details are temporarily unavailable.',[],true,['missingColumns'=>$missing]);$this->schemaChecked=true;
    }
}
