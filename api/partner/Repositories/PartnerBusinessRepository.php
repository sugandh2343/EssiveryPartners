<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\Helpers;
use Essivery\Partner\Domain\PartnerContext;
use PDO;
use PDOException;

final class PartnerBusinessRepository
{
    public function __construct(private PDO $pdo) {}
    public function get(PartnerContext $context): array
    {
        $q=$this->pdo->prepare('SELECT p.business_name,p.mobile business_contact,p.tax_number,m.description,m.logo_url,c.name parent_category,u.mobile verified_mobile FROM partners p JOIN users u ON u.id=p.user_id JOIN parent_categories c ON c.id=:category LEFT JOIN partner_marketplace_settings m ON m.partner_id=p.id WHERE p.id=:partner AND p.user_id=:user AND p.deleted_at IS NULL LIMIT 1');
        $q->execute(['category'=>$context->parentCategoryId,'partner'=>$context->businessPartnerId,'user'=>$context->authenticatedUserId]);
        return$q->fetch()?:[];
    }
    public function update(PartnerContext $context,string$name,string$contact,?string$description,?string$gst):void
    {
        $q=$this->pdo->prepare('UPDATE partners SET business_name=:name,mobile=:mobile,tax_number=:gst WHERE id=:partner AND user_id=:user AND deleted_at IS NULL');
        $q->execute(['name'=>$name,'mobile'=>$contact,'gst'=>$gst,'partner'=>$context->businessPartnerId,'user'=>$context->authenticatedUserId]);
        $sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? 'INSERT INTO partner_marketplace_settings(public_id,partner_id,description) VALUES(:public,:partner,:description) ON CONFLICT(partner_id) DO UPDATE SET description=excluded.description'
            : 'INSERT INTO partner_marketplace_settings(public_id,partner_id,description) VALUES(:public,:partner,:description) ON DUPLICATE KEY UPDATE description=VALUES(description)';
        try{$q=$this->pdo->prepare($sql);$q->execute(['public'=>Helpers::publicId('PMS'),'partner'=>$context->businessPartnerId,'description'=>$description]);}catch(PDOException$error){throw$error;}
    }
}
