<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerLocationRepository
{
    public function __construct(private PDO$pdo){}
    public function get(PartnerContext$c):array
    {
        $delivery=$c->deliveryPartnerId!==null;$table=$delivery?'delivery_partners':'partners';$id=$delivery?$c->deliveryPartnerId:$c->businessPartnerId;$line1=$delivery?'d.address_line':'d.address_line_1';
        $q=$this->pdo->prepare("SELECT $line1 address_line_1,d.address_line_2,d.locality,d.landmark,d.pincode,d.latitude,d.longitude,ci.name city_name,st.name state_name FROM $table d LEFT JOIN cities ci ON ci.id=d.city_id LEFT JOIN states st ON st.id=d.state_id WHERE d.id=:id AND d.user_id=:user AND d.deleted_at IS NULL LIMIT 1");$q->execute(['id'=>$id,'user'=>$c->authenticatedUserId]);return$q->fetch()?:[];
    }
    public function resolve(string$pincode,string$city,string$state):array
    {
        $q=$this->pdo->prepare("SELECT p.id pincode_id,p.service_area_id,c.id city_id,c.name city_name,s.id state_id,s.name state_name FROM pincodes p JOIN cities c ON c.id=p.city_id JOIN states s ON s.id=c.state_id WHERE p.pincode=:pincode AND LOWER(p.status)='active' AND LOWER(c.status)='active' AND LOWER(s.status)='active' LIMIT 1");$q->execute(['pincode'=>$pincode]);$row=$q->fetch();
        if($row)return$row+['knownPincode'=>true];
        $q=$this->pdo->prepare("SELECT c.id city_id,c.name city_name,s.id state_id,s.name state_name,NULL pincode_id,NULL service_area_id FROM cities c JOIN states s ON s.id=c.state_id WHERE LOWER(c.name)=LOWER(:city) AND LOWER(s.name)=LOWER(:state) AND LOWER(c.status)='active' AND LOWER(s.status)='active' LIMIT 1");$q->execute(['city'=>$city,'state'=>$state]);$row=$q->fetch();return$row?($row+['knownPincode'=>false]):[];
    }
    public function update(PartnerContext$c,array$v,array$taxonomy):void
    {
        $delivery=$c->deliveryPartnerId!==null;$table=$delivery?'delivery_partners':'partners';$line1=$delivery?'address_line':'address_line_1';$id=$delivery?$c->deliveryPartnerId:$c->businessPartnerId;
        $q=$this->pdo->prepare("UPDATE $table SET $line1=:line1,address_line_2=:line2,locality=:locality,landmark=:landmark,pincode=:pincode,city_id=:city,state_id=:state,latitude=:latitude,longitude=:longitude WHERE id=:id AND user_id=:user AND deleted_at IS NULL");$q->execute(['line1'=>$v['addressLine1'],'line2'=>$v['addressLine2']?:null,'locality'=>$v['locality'],'landmark'=>$v['landmark']?:null,'pincode'=>$v['pincode'],'city'=>$taxonomy['city_id'],'state'=>$taxonomy['state_id'],'latitude'=>$v['latitude'],'longitude'=>$v['longitude'],'id'=>$id,'user'=>$c->authenticatedUserId]);
    }
    public function referralSuggestion(PartnerContext$c):array
    {
        if($c->businessPartnerId===null)return[];$q=$this->pdo->prepare('SELECT address,locality,landmark,pincode,latitude,longitude FROM partner_referral_leads WHERE partner_id=:partner ORDER BY id DESC LIMIT 1');$q->execute(['partner'=>$c->businessPartnerId]);return$q->fetch()?:[];
    }
}
