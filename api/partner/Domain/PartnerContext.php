<?php
declare(strict_types=1);

namespace Essivery\Partner\Domain;

use Essivery\Api\Core\ApiException;

final class PartnerContext
{
    public int $authenticatedUserId;
    public string $authenticatedUserPublicId;
    public int $identityId;
    public string $identityPublicId;
    public string $identityType;
    public int $parentCategoryId;
    public string $moduleCode;
    public ?int $businessPartnerId;
    public ?string $businessPartnerPublicId;
    public ?int $deliveryPartnerId;
    public ?string $deliveryPartnerPublicId;
    public string $approvalStatus;
    public string $onboardingStatus;
    public string $accountStatus;
    public string $identityStatus;

    public function __construct(
        int $authenticatedUserId, string $authenticatedUserPublicId,
        int $identityId, string $identityPublicId, string $identityType,
        int $parentCategoryId, string $moduleCode,
        ?int $businessPartnerId, ?string $businessPartnerPublicId,
        ?int $deliveryPartnerId, ?string $deliveryPartnerPublicId,
        string $approvalStatus, string $onboardingStatus,
        string $accountStatus, string $identityStatus
    ) {
        foreach (get_defined_vars() as $name => $value) $this->{$name} = $value;
        $business = $this->businessPartnerId !== null && $this->businessPartnerPublicId !== null;
        $delivery = $this->deliveryPartnerId !== null && $this->deliveryPartnerPublicId !== null;
        if ($business === $delivery) {
            throw new ApiException(409, 'PARTNER_CONTEXT_AMBIGUOUS', 'Partner ownership mapping is ambiguous.', 'Your Partner profile could not be resolved safely.');
        }
    }

    public function publicPayload(): array
    {
        $isDelivery = $this->deliveryPartnerId !== null;
        return [
            'identity' => [
                'publicId' => $this->identityPublicId,
                'type' => $this->identityType,
                'module' => $this->moduleCode,
                'parentCategoryId' => $this->parentCategoryId,
            ],
            'partner' => [
                'publicId' => $isDelivery ? $this->deliveryPartnerPublicId : $this->businessPartnerPublicId,
                'kind' => $isDelivery ? 'delivery' : 'business',
                'approvalStatus' => $this->approvalStatus,
                'onboardingStatus' => $this->onboardingStatus,
            ],
            'capabilities' => [],
            'serverTime' => gmdate('Y-m-d\TH:i:s.000\Z'),
        ];
    }
}
