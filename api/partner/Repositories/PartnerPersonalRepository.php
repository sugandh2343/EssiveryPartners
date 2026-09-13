<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Partner\Domain\PartnerContext;
use PDO;

final class PartnerPersonalRepository
{
    public function __construct(private PDO $pdo) {}

    public function get(PartnerContext $context): array
    {
        if ($context->deliveryPartnerId !== null) {
            $q = $this->pdo->prepare('SELECT d.name owner_name,d.email,u.mobile FROM delivery_partners d JOIN users u ON u.id=d.user_id WHERE d.id=:owner AND d.user_id=:user AND d.deleted_at IS NULL LIMIT 1');
            $q->execute(['owner' => $context->deliveryPartnerId, 'user' => $context->authenticatedUserId]);
        } else {
            $q = $this->pdo->prepare('SELECT p.owner_name,p.email,u.mobile FROM partners p JOIN users u ON u.id=p.user_id WHERE p.id=:owner AND p.user_id=:user AND p.deleted_at IS NULL LIMIT 1');
            $q->execute(['owner' => $context->businessPartnerId, 'user' => $context->authenticatedUserId]);
        }
        return $q->fetch() ?: [];
    }

    public function update(PartnerContext $context, string $ownerName, ?string $email): void
    {
        $table = $context->deliveryPartnerId !== null ? 'delivery_partners' : 'partners';
        $name = $context->deliveryPartnerId !== null ? 'name' : 'owner_name';
        $owner = $context->deliveryPartnerId ?? $context->businessPartnerId;
        $q = $this->pdo->prepare("UPDATE $table SET $name=:name,email=:email WHERE id=:owner AND user_id=:user AND deleted_at IS NULL");
        $q->execute(['name' => $ownerName, 'email' => $email, 'owner' => $owner, 'user' => $context->authenticatedUserId]);
    }
}
