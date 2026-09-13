<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerHomeServiceRepository;
use PDO;
use Throwable;

final class PartnerHomeServiceService
{
    public function __construct(private PDO $pdo,private PartnerHomeServiceRepository $repository,private PartnerSetupService $setup) {}
    public function get(PartnerContext $context):array{$this->applicable($context);return$this->state($context);}
    public function update(PartnerContext $context,array $input,?callable $audit=null):array
    {
        $this->applicable($context);$codes=$input['services']??null;$preference=strtoupper(trim((string)($input['setupPreference']??'')));$fields=[];
        if(!is_array($codes))$fields['services']='Select between 1 and 20 services.';
        else{$codes=array_values(array_unique(array_map(fn($x)=>strtolower(trim((string)$x)),$codes)));if(count($codes)<1||count($codes)>20)$fields['services']='Select between 1 and 20 services.';}
        if(!in_array($preference,['SELF','ASSISTED'],true))$fields['setupPreference']='Choose how you want to prepare your services.';
        if(($input['confirmed']??null)!==true)$fields['confirmed']='Confirm your Home Service selections.';
        if($fields)throw new ApiException(422,'VALIDATION_ERROR','Home Service Setup validation failed.','Review your Home Service Setup choices.',$fields);
        $resolved=$this->repository->resolveServices($codes);if(count($resolved)!==count($codes))throw new ApiException(422,'VALIDATION_ERROR','One or more Home Services are invalid.','Choose services from the available list.',['services'=>'Unknown or inactive service selected.']);
        $this->pdo->beginTransaction();try{$before=$this->repository->profile($context);$this->repository->save($context,$preference,$resolved);$setup=$this->setup->markHomeServiceComplete($context);if($audit)$audit(['serviceCodes'=>$codes,'serviceCount'=>count($codes),'setupPreference'=>$preference,'preferenceChanged'=>($before['setup_preference']??null)!==$preference]);$this->pdo->commit();return$this->project($this->repository->profile($context),$this->repository->options(),$setup);}catch(Throwable$error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$error;}
    }
    private function state(PartnerContext $context):array{return$this->project($this->repository->profile($context),$this->repository->options(),$this->setup->load($context));}
    private function project(array $row,array $options,array $setup):array
    {
        $groups=[];foreach($options as$option){$key=$option['category_code'].'/'.($option['subcategory_code']??'other');if(!isset($groups[$key]))$groups[$key]=['category'=>['code'=>$option['category_code'],'name'=>$option['category_name']],'subcategory'=>$option['subcategory_code']?['code'=>$option['subcategory_code'],'name'=>$option['subcategory_name']]:null,'services'=>[]];$groups[$key]['services'][]=['code'=>$option['slug'],'name'=>$option['name']];}
        return['homeService'=>['businessName'=>$row['business_name']??'','category'=>['code'=>'home_service','name'=>'Home Services'],'services'=>array_map(fn($x)=>['code'=>$x['slug'],'name'=>$x['name']],$row['services']??[]),'setupPreference'=>$row['setup_preference']??null,'confirmed'=>!empty($row['setup_confirmed_at'])],'options'=>['groups'=>array_values($groups),'maximumServices'=>20],'step'=>$this->step($setup),'setup'=>$setup];
    }
    private function applicable(PartnerContext $context):void{if($context->deliveryPartnerId!==null||$context->identityType!=='home_service'||$context->moduleCode!=='HOME_SERVICE')throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Home Service Setup does not apply to this Partner identity.','Home Service Setup is not required for this Partner type.');}
    private function step(array $setup):array{return array_values(array_filter($setup['steps'],fn($x)=>$x['code']==='homeServiceSetup'))[0]??['code'=>'homeServiceSetup','status'=>'notStarted'];}
}

