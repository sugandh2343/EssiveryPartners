<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerBusinessRepository;
use PDO;
use Throwable;

final class PartnerBusinessService
{
    public function __construct(private PDO$pdo,private PartnerBusinessRepository$business,private PartnerSetupService$setup){}
    public function get(PartnerContext$context):array
    {
        $this->applicable($context);$r=$this->business->get($context);if(!$r)throw new ApiException(404,'PARTNER_BUSINESS_NOT_FOUND','Owned Partner business was not found.','Your business profile could not be loaded.');$name=trim((string)$r['business_name']);$generated=mb_strtolower(str_replace('_',' ',$context->identityType).' partner');if(in_array(mb_strtolower($name),['partner','business','shop',$generated],true))$name='';$setup=$this->setup->load($context);$step=array_values(array_filter($setup['steps'],fn(array$s):bool=>$s['code']==='business'))[0]??['code'=>'business','status'=>'notStarted'];return['businessName'=>$name,'businessContact'=>$r['business_contact'],'verifiedMobile'=>$r['verified_mobile'],'whatsappNumber'=>null,'description'=>$r['description']?:null,'yearsInBusiness'=>null,'gstNumber'=>$r['tax_number']?:null,'logo'=>$r['logo_url']?:null,'parentCategory'=>$r['parent_category'],'businessType'=>$r['parent_category'].' Partner','descriptionTemplate'=>$this->template($context,$r['parent_category']),'step'=>$step,'setup'=>$setup];
    }
    public function update(PartnerContext$context,array$input):array
    {
        $this->applicable($context);$rawName=trim((string)($input['businessName']??''));$hasMarkup=$rawName!==strip_tags($rawName);$name=preg_replace('/\s+/u',' ',trim(strip_tags($rawName)))??'';$contact=$this->mobile((string)($input['businessContact']??''));$description=trim(strip_tags((string)($input['description']??'')));$gst=strtoupper(trim((string)($input['gstNumber']??'')));$fields=[];if($hasMarkup||mb_strlen($name)<2||mb_strlen($name)>120||!preg_match("/^[\p{L}\p{N} .&'\-]+$/u",$name))$fields['businessName']='Use 2 to 120 letters, numbers, spaces, &, hyphen, dot, or apostrophe; HTML is not allowed.';if($contact===null)$fields['businessContact']='Enter a valid Indian business contact number.';if(mb_strlen($description)>500)$fields['description']='Business description must be 500 characters or fewer.';if($gst!==''&&!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',$gst))$fields['gstNumber']='Enter a valid 15-character GST number.';if($fields)throw new ApiException(422,'VALIDATION_ERROR','Business Details validation failed.','Review the highlighted Business Details fields.',$fields);
        $this->pdo->beginTransaction();try{$this->business->update($context,$name,$contact,$description===''?null:$description,$gst===''?null:$gst);$summary=$this->setup->markBusinessComplete($context);$this->pdo->commit();return$this->get($context)+['setup'=>$summary];}catch(Throwable$error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }
    public function descriptionTemplate(PartnerContext$context):array{$this->applicable($context);$r=$this->business->get($context);return['description'=>$this->template($context,(string)($r['parent_category']??'business'))];}
    private function applicable(PartnerContext$c):void{if($c->deliveryPartnerId!==null)throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Business Details does not apply to Delivery Partners.','Continue to Location setup.');}
    private function mobile(string$value):?string{$d=preg_replace('/\D+/','',$value)??'';if(strlen($d)===10&&preg_match('/^[6-9]/',$d))$d='91'.$d;if(!preg_match('/^91[6-9]\d{9}$/',$d))return null;return'+'.$d;}
    private function template(PartnerContext$c,string$category):string{return match($c->moduleCode){'RESTAURANT'=>"$category serving delicious food and popular cuisines with convenient ordering and delivery through Essivery.",'HOME_SERVICE'=>"Professional $category services delivered by trusted professionals with convenient booking through Essivery.",default=>"Your trusted $category for fresh groceries, vegetables, daily essentials, and fast delivery through Essivery."};}
}
