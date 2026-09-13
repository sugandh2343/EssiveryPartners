<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;use Essivery\Partner\Domain\PartnerContext;use Essivery\Partner\Repositories\PartnerRetailRepository;use PDO;use Throwable;

final class PartnerRetailService
{
    private const IDENTITIES=['grocery','vegetable','pharmacy','fashion','electronics'];
    public function __construct(private PDO$pdo,private PartnerRetailRepository$retail,private PartnerSetupService$setup){}
    public function get(PartnerContext$c):array{$this->applicable($c);return$this->state($c);}
    public function update(PartnerContext$c,array$i,?callable$beforeCommit=null):array
    {
        $this->applicable($c);$mode=strtoupper(trim((string)($i['catalogueSetupMode']??'')));$fields=[];if(!in_array($mode,['SELF','ASSISTED'],true))$fields['catalogueSetupMode']='Choose how you want to prepare your catalogue.';if(($i['confirmed']??null)!==true)$fields['confirmed']='Confirm the selected store category and information.';if($fields)throw new ApiException(422,'VALIDATION_ERROR','Retail Setup validation failed.','Review your Retail Setup choices.',$fields);
        $this->pdo->beginTransaction();try{$before=$this->retail->context($c);$row=$this->retail->save($c,$mode);$setup=$this->setup->markRetailComplete($c);if($beforeCommit)$beforeCommit(['categoryCode'=>$c->identityType,'catalogueSetupMode'=>$mode,'modeChanged'=>($before['catalogue_setup_mode']??null)!==$mode]);$this->pdo->commit();return$this->project($c,$row,$setup);}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }
    private function state(PartnerContext$c):array{$row=$this->retail->context($c);if(!$row)throw new ApiException(409,'RETAIL_CATEGORY_UNAVAILABLE','The selected Retail category could not be resolved.','Your store category could not be confirmed.');return$this->project($c,$row,$this->setup->load($c));}
    private function project(PartnerContext$c,array$row,array$setup):array{return['retail'=>['category'=>['code'=>$c->identityType,'slug'=>$row['category_slug'],'name'=>$row['category_name'],'iconUrl'=>$row['icon_url']?:null],'businessName'=>$row['business_name'],'catalogueSetupMode'=>$row['catalogue_setup_mode']?:null,'confirmed'=>!empty($row['retail_confirmed_at'])],'step'=>$this->step($setup),'setup'=>$setup];}
    private function applicable(PartnerContext$c):void{if($c->deliveryPartnerId!==null||$c->moduleCode!=='RETAIL'||!in_array($c->identityType,self::IDENTITIES,true))throw new ApiException(409,'PARTNER_SETUP_NOT_APPLICABLE','Retail Setup does not apply to this Partner identity.','Retail Setup is not required for this Partner type.');}
    private function step(array$s):array{return array_values(array_filter($s['steps'],fn(array$x):bool=>$x['code']==='retailSetup'))[0]??['code'=>'retailSetup','status'=>'notStarted'];}
}
