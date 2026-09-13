<?php
declare(strict_types=1);

namespace Essivery\Partner\Repositories;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Helpers;
use PDO;
use PDOException;

final class PartnerContextRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function userIdentity(int $userId, int $identityId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id user_id,u.public_id user_public_id,u.mobile,u.parent_category_id,u.status account_status,
                    i.id identity_id,i.public_id identity_public_id,i.identity_type,i.status identity_status
             FROM users u JOIN user_identities i ON i.user_id=u.id
             WHERE u.id=:user AND i.id=:identity LIMIT 1'
        );
        $statement->execute(['user' => $userId, 'identity' => $identityId]);
        return $statement->fetch() ?: null;
    }

    public function parentCategorySlug(int $id): ?string
    {
        if ($id <= 0) return null;
        $statement = $this->pdo->prepare("SELECT slug FROM parent_categories WHERE id=:id AND status='active' AND deleted_at IS NULL LIMIT 1");
        $statement->execute(['id' => $id]);
        $slug = $statement->fetchColumn();
        return $slug === false ? null : (string) $slug;
    }

    public function authenticatedMobile(int $userId): string
    {
        $statement = $this->pdo->prepare('SELECT mobile FROM users WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $mobile = trim((string) $statement->fetchColumn());
        if ($mobile === '') {
            throw new ApiException(409, 'PARTNER_MOBILE_UNAVAILABLE', 'Authenticated Partner mobile is unavailable.', 'Your verified mobile number could not be confirmed.');
        }
        return $mobile;
    }

    public function businessPartners(int $userId, bool $lock = false): array
    {
        $statement = $this->pdo->prepare('SELECT id,public_id,user_id,onboarding_status,approval_status,status FROM partners WHERE user_id=:user AND deleted_at IS NULL ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function deliveryPartners(int $userId, bool $lock = false): array
    {
        $statement = $this->pdo->prepare('SELECT id,public_id,user_id,onboarding_status,approval_status,status FROM delivery_partners WHERE user_id=:user AND deleted_at IS NULL ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function assertBusinessSchema(): void
    {
        $this->assertCanonicalTable('partners', ['id','public_id','user_id','business_name','owner_name','mobile','onboarding_status','approval_status','status'], 'user_id');
    }

    public function assertDeliverySchema(): void
    {
        $this->assertCanonicalTable('delivery_partners', ['id','public_id','user_id','name','mobile','onboarding_status','approval_status','status'], 'user_id');
    }

    public function createBusiness(array $identity): array
    {
        $statement = $this->pdo->prepare('INSERT INTO partners(public_id,user_id,business_name,owner_name,mobile) VALUES(:public,:user,:business,:owner,:mobile)');
        try {
            $statement->execute([
                'public' => Helpers::publicId('PTR'),
                'user' => $identity['user_id'],
                'business' => $this->identityLabel($identity['identity_type']) . ' Partner',
                'owner' => 'Partner',
                'mobile' => $identity['mobile'],
            ]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
        }
        $rows = $this->businessPartners((int) $identity['user_id'], true);
        return $this->one($rows);
    }

    public function createDelivery(array $identity): array
    {
        $statement = $this->pdo->prepare('INSERT INTO delivery_partners(public_id,user_id,name,mobile) VALUES(:public,:user,:name,:mobile)');
        try {
            $statement->execute(['public' => Helpers::publicId('DPR'), 'user' => $identity['user_id'], 'name' => 'Delivery Partner', 'mobile' => $identity['mobile']]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
        }
        $rows = $this->deliveryPartners((int) $identity['user_id'], true);
        return $this->one($rows);
    }

    private function assertCanonicalTable(string $table, array $columns, string $uniqueColumn): void
    {
        $found = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table');
        $found->execute(['table' => $table]);
        $actual = array_column($found->fetchAll(), 'column_name');
        if (array_diff($columns, $actual)) {
            throw new ApiException(503, 'CONFIGURATION_ERROR', 'Canonical Partner schema is unavailable.', 'Essivery Partners is temporarily unavailable.', [], true);
        }
        $unique = $this->pdo->prepare(
            'SELECT COUNT(*) FROM (
               SELECT index_name,MIN(non_unique) non_unique,COUNT(*) columns_count,
                      GROUP_CONCAT(column_name ORDER BY seq_in_index) columns_list
               FROM information_schema.statistics
               WHERE table_schema=DATABASE() AND table_name=:table
               GROUP BY index_name
             ) indexes_found
             WHERE non_unique=0 AND columns_count=1 AND columns_list=:column'
        );
        $unique->execute(['table' => $table, 'column' => $uniqueColumn]);
        if ((int) $unique->fetchColumn() !== 1) {
            throw new ApiException(503, 'CONFIGURATION_ERROR', 'Canonical Partner ownership uniqueness is unavailable.', 'Essivery Partners is temporarily unavailable.', [], true);
        }
    }

    private function one(array $rows): array
    {
        if (count($rows) !== 1) {
            throw new ApiException(409, 'PARTNER_CONTEXT_AMBIGUOUS', 'Partner ownership mapping is ambiguous.', 'Your Partner profile could not be resolved safely.');
        }
        return $rows[0];
    }

    private function identityLabel(string $identity): string
    {
        return ucwords(str_replace('_', ' ', $identity));
    }
}
