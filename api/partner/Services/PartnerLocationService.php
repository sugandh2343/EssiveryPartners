<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerLocationRepository;
use PDO;
use Throwable;

final class PartnerLocationService
{
    public function __construct(private PDO$pdo,private PartnerLocationRepository$locations,private PartnerSetupService$setup){}
    public function get(PartnerContext$c):array
    {
        $r=$this->locations->get($c);if(!$r)throw new ApiException(404,'PARTNER_LOCATION_OWNER_NOT_FOUND','Owned Partner location record was not found.','Your Location could not be loaded.');$setup=$this->setup->load($c);$step=array_values(array_filter($setup['steps'],fn(array$s):bool=>$s['code']==='location'))[0]??['code'=>'location','status'=>'notStarted'];$official=['addressLine1'=>$r['address_line_1']??'','addressLine2'=>$r['address_line_2']??'','locality'=>$r['locality']??'','landmark'=>$r['landmark']??'','pincode'=>$r['pincode']??'','city'=>['name'=>$r['city_name']??''],'state'=>['name'=>$r['state_name']??''],'latitude'=>$r['latitude']===null?'':(string)$r['latitude'],'longitude'=>$r['longitude']===null?'':(string)$r['longitude']];$suggestion=[];if(!$this->hasOfficial($official)){$ref=$this->locations->referralSuggestion($c);$suggestion=['addressLine1'=>$ref['address']??'','locality'=>$ref['locality']??'','landmark'=>$ref['landmark']??'','pincode'=>$ref['pincode']??'','latitude'=>isset($ref['latitude'])?(string)$ref['latitude']:'','longitude'=>isset($ref['longitude'])?(string)$ref['longitude']:''];}$official['serviceabilityStatus']='notConfigured';$official['referralSuggestion']=array_filter($suggestion,fn($v)=>$v!=='');$official['step']=$step;$official['setup']=$setup;return$official;
    }
    public function update(PartnerContext$c,array$input,?callable$beforeCommit=null):array
    {
        $v=[];foreach(['addressLine1','addressLine2','locality','landmark','city','state']as$key)$v[$key]=$this->text((string)($input[$key]??''));$v['pincode']=trim((string)($input['pincode']??''));$v['latitude']=trim((string)($input['latitude']??''));$v['longitude']=trim((string)($input['longitude']??''));$confirmed=($input['mapConfirmed']??null)===true;$fields=[];
        if(mb_strlen($v['addressLine1'])<3||mb_strlen($v['addressLine1'])>255)$fields['addressLine1']='Address Line 1 must be between 3 and 255 characters.';if(mb_strlen($v['addressLine2'])>255)$fields['addressLine2']='Address Line 2 must be 255 characters or fewer.';if(mb_strlen($v['locality'])<2||mb_strlen($v['locality'])>150)$fields['locality']='Locality must be between 2 and 150 characters.';if(mb_strlen($v['landmark'])>150)$fields['landmark']='Landmark must be 150 characters or fewer.';if(!preg_match('/^[1-9]\d{5}$/',$v['pincode']))$fields['pincode']='Enter a valid six-digit Indian pincode.';if($v['city']===''||mb_strlen($v['city'])>120)$fields['city']='Choose a valid city.';if($v['state']===''||mb_strlen($v['state'])>120)$fields['state']='Choose a valid state.';if(!$this->decimal($v['latitude'],-90,90))$fields['latitude']='Latitude must be between -90 and 90.';if(!$this->decimal($v['longitude'],-180,180))$fields['longitude']='Longitude must be between -180 and 180.';if(!$confirmed)$fields['mapConfirmed']='Confirm the map position before saving.';if($fields)throw$this->validation($fields);
        $taxonomy=$this->locations->resolve($v['pincode'],$v['city'],$v['state']);if(!$taxonomy)throw$this->validation(['city'=>'City and state could not be resolved from Essivery taxonomy.','state'=>'Choose a supported city and state.']);if($taxonomy['knownPincode']&&(!hash_equals(mb_strtolower($taxonomy['city_name']),mb_strtolower($v['city']))||!hash_equals(mb_strtolower($taxonomy['state_name']),mb_strtolower($v['state']))))throw$this->validation(['pincode'=>'This pincode maps to '.$taxonomy['city_name'].', '.$taxonomy['state_name'].'. Please confirm the address.']);
        $v['latitude']=number_format((float)$v['latitude'],7,'.','');$v['longitude']=number_format((float)$v['longitude'],7,'.','');$this->pdo->beginTransaction();try{$this->locations->update($c,$v,$taxonomy);$summary=$this->setup->markLocationComplete($c);if($beforeCommit)$beforeCommit();$this->pdo->commit();$result=$this->get($c);$result['setup']=$summary;return$result;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }
    private function text(string$v):string{return preg_replace('/\s+/u',' ',trim(strip_tags($v)))??'';}
    private function decimal(string$v,float$min,float$max):bool{return preg_match('/^-?\d{1,3}(?:\.\d{1,12})?$/',$v)===1&&(float)$v>=$min&&(float)$v<=$max;}
    private function validation(array$f):ApiException{return new ApiException(422,'VALIDATION_ERROR','Location validation failed.','Review the highlighted Location fields.',$f);}
    private function hasOfficial(array$v):bool{return$v['addressLine1']!==''||$v['locality']!==''||$v['pincode']!==''||$v['latitude']!==''||$v['longitude']!=='';}
}
