<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Policies\PartnerIdentityPolicy;
use Essivery\Partner\Repositories\PartnerContextRepository;

final class PartnerContextService
{
    private PartnerIdentityPolicy $policy;
    private PartnerContextRepository $repository;

    public function __construct(PartnerContextRepository $repository, array $identityMap)
    {
        $this->repository = $repository;
        $this->policy = new PartnerIdentityPolicy($identityMap);
    }

    public function resolve(array $auth, bool $allowUninitialized): PartnerContext|array
    {
        $identity = $this->identity($auth);
        $module = $this->policy->moduleFor($identity['identity_type']);
        $parent = (int) ($identity['parent_category_id'] ?? 0);
        $this->policy->assertCategory($identity['identity_type'], $parent, $this->repository->parentCategorySlug($parent));

        $businessRows = $this->repository->businessPartners((int) $identity['user_id']);
        $deliveryRows = $this->repository->deliveryPartners((int) $identity['user_id']);
        if (count($businessRows) > 1 || count($deliveryRows) > 1 || ($businessRows && $deliveryRows)) {
            throw new ApiException(409, 'PARTNER_CONTEXT_AMBIGUOUS', 'Partner ownership mapping is ambiguous.', 'Your Partner profile could not be resolved safely.');
        }
        $deliveryIdentity = $identity['identity_type'] === 'delivery_partner';
        $expected = $deliveryIdentity ? $deliveryRows : $businessRows;
        $wrong = $deliveryIdentity ? $businessRows : $deliveryRows;
        if ($wrong) {
            throw new ApiException(409, 'PARTNER_CONTEXT_AMBIGUOUS', 'Partner identity maps to the wrong core type.', 'Your Partner profile could not be resolved safely.');
        }
        if (!$expected) {
            if ($allowUninitialized) return ['identity' => $identity, 'module' => $module];
            throw new ApiException(409, 'PARTNER_CONTEXT_NOT_INITIALIZED', 'Partner core is not initialized.', 'Finish setting up your Partner profile to continue.', [], true);
        }
        return $this->context($identity, $module, $deliveryIdentity ? null : $expected[0], $deliveryIdentity ? $expected[0] : null);
    }

    public function context(array $identity, string $module, ?array $business, ?array $delivery): PartnerContext
    {
        $core = $business ?? $delivery;
        return new PartnerContext(
            (int) $identity['user_id'], (string) $identity['user_public_id'],
            (int) $identity['identity_id'], (string) $identity['identity_public_id'],
            (string) $identity['identity_type'], (int) ($identity['parent_category_id'] ?? 0), $module,
            $business ? (int) $business['id'] : null, $business['public_id'] ?? null,
            $delivery ? (int) $delivery['id'] : null, $delivery['public_id'] ?? null,
            (string) $core['approval_status'], (string) $core['onboarding_status'],
            (string) $identity['account_status'], (string) $identity['identity_status'],
        );
    }

    private function identity(array $auth): array
    {
        $row = $this->repository->userIdentity((int) $auth['user_id'], (int) $auth['identity_id']);
        if (!$row || $row['user_public_id'] !== $auth['user_public_id'] || $row['identity_public_id'] !== $auth['identity_public_id']) {
            throw new ApiException(401, 'SESSION_REVOKED', 'Session identity is no longer available.', 'Please sign in again.');
        }
        return $row;
    }
}
