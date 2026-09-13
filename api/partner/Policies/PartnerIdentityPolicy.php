<?php
declare(strict_types=1);

namespace Essivery\Partner\Policies;

use Essivery\Api\Core\ApiException;

final class PartnerIdentityPolicy
{
    private array $identityMap;

    public function __construct(array $identityMap)
    {
        $this->identityMap = $identityMap;
    }

    public function moduleFor(string $identityType): string
    {
        if ($identityType === 'customer') {
            throw new ApiException(403, 'PARTNER_IDENTITY_REQUIRED', 'A Partner identity is required.', 'Choose a Partner identity to continue.');
        }
        if (!isset($this->identityMap[$identityType])) {
            throw new ApiException(403, 'PARTNER_IDENTITY_UNSUPPORTED', 'This identity is not supported by the Partner API.', 'This account cannot use Essivery Partners.');
        }
        return $this->identityMap[$identityType];
    }

    public function assertCategory(string $identityType, int $parentCategoryId, ?string $parentSlug): void
    {
        if ($identityType === 'delivery_partner') {
            if ($parentCategoryId !== 0) {
                throw $this->conflict();
            }
            return;
        }

        if ($parentCategoryId <= 0 || $parentSlug === null) {
            throw $this->conflict();
        }

        $slug = strtolower(str_replace([' ', '_'], '-', $parentSlug));
        $allowed = match ($identityType) {
            'vegetable' => ['vegetable', 'vegetables', 'fruits-vegetables', 'fruits-and-vegetables', 'fresh-fruits-vegetables', 'fresh-fruits-and-vegetables'],
            'home_service' => ['home-service', 'home-services'],
            'restaurant' => ['restaurant', 'restaurants'],
            default => [str_replace('_', '-', $identityType)],
        };
        if (!in_array($slug, $allowed, true)) {
            throw $this->conflict();
        }
    }

    private function conflict(): ApiException
    {
        return new ApiException(409, 'PARTNER_CONTEXT_AMBIGUOUS', 'Identity and Parent Category mapping conflict.', 'Your selected Partner identity could not be verified.');
    }
}
