<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerBankAccountRepository;
use Essivery\Partner\Repositories\PartnerDocumentRepository;
use PDO;
use Throwable;

final class PartnerBankService
{
    public function __construct(private PDO$pdo,private PartnerBankAccountRepository$banks,private PartnerDocumentRepository$documents,private PartnerBankEncryption$crypto,private PartnerSetupService$setup){}
    public function get(PartnerContext$c):array{return$this->state($c,true);}
    public function update(PartnerContext$c,array$input,?callable$beforeCommit=null):array
    {
        $v=$this->validate($input);$this->pdo->beginTransaction();try{$old=$this->banks->current($c,true);$changed=true;
            if($old){$current=$this->crypto->decrypt((string)$old['account_number_encrypted'],(int)$old['encryption_version']);$changed=!hash_equals($current,$v['account'])||$old['account_holder_name']!==$v['holder']||$old['ifsc_code']!==$v['ifsc']||in_array($this->review((string)$old['review_status']),['rejected','needs_correction'],true);unset($current);}
            if($changed)$this->banks->replace($c,['holder'=>$v['holder'],'encrypted'=>$this->crypto->encrypt($v['account']),'last4'=>$v['last4'],'version'=>PartnerBankEncryption::CURRENT_BANK_ENCRYPTION_VERSION,'ifsc'=>$v['ifsc']]);
            $proof=$this->proof($c);$proofReview=$proof?$this->review((string)$proof['review_status']):null;$proofCorrection=in_array($proofReview,['rejected','needs_correction'],true);$complete=(bool)$proof&&!$proofCorrection;$setup=$this->setup->syncBankState($c,$complete,$proofCorrection);if($beforeCommit)$beforeCommit(['last4'=>$v['last4'],'ifsc'=>$v['ifsc'],'reviewState'=>$changed?'pending':$this->review((string)($old['review_status']??'pending')),'changed'=>$changed,'completed'=>$complete]);$this->pdo->commit();unset($v['account']);$result=$this->state($c,false);$result['setup']=$setup;$result['step']=$this->step($setup);return$result;
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }
    private function state(PartnerContext$c,bool$sync):array
    {
        $row=$this->banks->current($c);$review=$row?$this->review((string)$row['review_status']):null;$proof=$this->proof($c);$proofReview=$proof?$this->review((string)$proof['review_status']):null;$correction=in_array($review,['rejected','needs_correction'],true)||in_array($proofReview,['rejected','needs_correction'],true);$complete=(bool)$row&&(bool)$proof&&!$correction;$setup=$sync?$this->setup->syncBankState($c,$complete,$correction):$this->setup->load($c);
        return['bank'=>['hasBankDetails'=>(bool)$row,'accountHolderName'=>$row['account_holder_name']??'','maskedAccountNumber'=>$row?$this->crypto->mask((string)$row['account_number_last4']):null,'last4'=>$row['account_number_last4']??null,'ifsc'=>$row['ifsc_code']??'','reviewStatus'=>$review,'updatedAt'=>!empty($row['updated_at'])?gmdate('c',strtotime((string)$row['updated_at'])):null],'bankProof'=>$proof?['uploaded'=>true,'reference'=>$proof['public_id'],'fileType'=>str_ends_with(strtolower((string)$proof['file_reference']),'.pdf')?'pdf':'image','reviewStatus'=>$proofReview,'uploadedAt'=>!empty($proof['created_at'])?gmdate('c',strtotime((string)$proof['created_at'])):null,'viewUrl'=>'/setup/documents/'.$proof['public_id'].'/file']:['uploaded'=>false,'reference'=>null,'fileType'=>null,'reviewStatus'=>null,'uploadedAt'=>null,'viewUrl'=>null],'step'=>$this->step($setup),'setup'=>$setup];
    }
    private function validate(array$i):array
    {
        $holder=preg_replace('/\s+/u',' ',trim((string)($i['accountHolderName']??'')))??'';$account=trim((string)($i['accountNumber']??''));$confirm=trim((string)($i['confirmAccountNumber']??''));$ifsc=strtoupper(trim((string)($i['ifsc']??'')));$f=[];
        if(strlen($holder)<2||strlen($holder)>150||!preg_match("/^[\p{L} .'-]+$/u",$holder))$f['accountHolderName']='Enter a valid account holder name (2–150 characters).';if(!preg_match('/^\d{6,20}$/',$account))$f['accountNumber']='Enter a 6–20 digit account number.';if($confirm!==$account)$f['confirmAccountNumber']='Account numbers do not match.';if(!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/',$ifsc))$f['ifsc']='Enter a valid IFSC code.';if($f)throw new ApiException(422,'VALIDATION_ERROR','Bank details validation failed.','Review your Bank details.',$f);
        return['holder'=>$holder,'account'=>$account,'last4'=>substr($account,-4),'ifsc'=>$ifsc];
    }
    private function review(string$s):string{return match(strtolower($s)){'approved'=>'approved','rejected'=>'rejected','correction_required','needs_correction'=>'needs_correction',default=>'pending'};}
    private function proof(PartnerContext$c):array{foreach($this->documents->current($c)as$row)if(strtoupper((string)$row['document_type'])==='BANK_PROOF')return$row;return[];}
    private function step(array$s):array{return array_values(array_filter($s['steps'],fn(array$x):bool=>$x['code']==='bank'))[0]??['code'=>'bank','status'=>'notStarted'];}
}
