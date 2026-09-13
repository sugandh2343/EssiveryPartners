<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use DomainException;
use Essivery\Api\Core\ApiException;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerContextRepository;
use OutOfBoundsException;
use PDO;

final class PartnerReferralService implements ReferralAutoClaimInterface
{
    private const SUPPORTED_MODULES = ['RETAIL', 'RESTAURANT', 'HOME_SERVICE', 'TAXI'];
    private object $shared;

    public function __construct(
        private PDO $pdo,
        private PartnerContextRepository $partners,
        private ?object $logger = null,
        ?object $shared = null,
        ?string $helpersPath = null,
    ) {
        if ($shared !== null) {
            $this->shared = $shared;
            return;
        }
        $helpers = rtrim((string) ($helpersPath ?: getenv('ESSIVERY_ADMIN_API_PATH') ?: dirname(__DIR__, 2)), '/\\') . '/helpers';
        if (!is_file($helpers . '/partnerReferralRepository.php') || !is_file($helpers . '/publicId.php')) {
            throw new ApiException(503, 'REFERRAL_CONFIGURATION_ERROR', 'Shared Partner Referral engine is unavailable.', 'Referral services are temporarily unavailable.', [], true);
        }
        require_once $helpers . '/publicId.php';
        require_once $helpers . '/partnerReferralRepository.php';
        $this->shared = new \PartnerReferralRepository($pdo);
    }

    public function claim(PartnerContext $context, string $token): array
    {
        $this->assertApplicable($context);
        $resolved = $this->shared->resolve($token);
        if (empty($resolved['valid'])) return $this->terminal((string) ($resolved['state'] ?? 'INVALID'));
        if (strtoupper((string) ($resolved['module'] ?? '')) !== $context->moduleCode) {
            throw new ApiException(409, 'REFERRAL_MODULE_MISMATCH', 'Referral module does not match the authenticated Partner identity.', 'Choose the business category from your referral or continue without it.');
        }
        $state = strtoupper((string) ($resolved['state'] ?? 'INVALID'));
        if (!in_array($state, ['REFERRAL_SENT', 'REGISTERED', 'ACTIVATED'], true)) return $this->terminal($state);
        if ($state === 'REFERRAL_SENT' && empty($resolved['canRegister'])) return $this->terminal('EXPIRED');
        try {
            $result = $this->shared->claim(
                (int) $context->businessPartnerId,
                $this->partners->authenticatedMobile($context->authenticatedUserId),
                $token,
                $this->scope($context),
            );
            $this->audit('Partner referral claim success', $context, 'success');
            return $this->projection($result, true);
        } catch (DomainException $error) {
            $this->audit('Partner referral claim rejected', $context, 'rejected');
            throw new ApiException(409, 'REFERRAL_NOT_CLAIMABLE', $error->getMessage(), 'This referral cannot be linked to this account.');
        } catch (OutOfBoundsException) {
            throw new ApiException(404, 'REFERRAL_NOT_FOUND', 'No claimable Partner Referral found.', 'This referral is not available.');
        }
    }

    public function context(PartnerContext $context): array
    {
        if (!$this->applicable($context)) return ['state' => 'notApplicable', 'linked' => false, 'prefill' => (object) []];
        $statement = $this->pdo->prepare("SELECT business_name,business_module,locality,address,pincode,latitude,longitude,landmark,referral_status FROM partner_referral_leads WHERE partner_id=:partner ORDER BY id DESC LIMIT 1");
        $statement->execute(['partner' => $context->businessPartnerId]);
        $row = $statement->fetch();
        return $row ? $this->projection($row, true) : ['state' => 'none', 'linked' => false, 'prefill' => (object) []];
    }

    public function continueWithoutReferral(PartnerContext $context): array
    {
        return ['continued' => true, 'state' => $this->applicable($context) ? 'none' : 'notApplicable'];
    }

    public function afterBootstrap(PartnerContext $context): void
    {
        if (!$this->applicable($context)) return;
        $enabled = (int) $this->pdo->query('SELECT allow_mobile_auto_claim FROM partner_referral_config WHERE id=1')->fetchColumn() === 1;
        if (!$enabled) return;
        try {
            $this->shared->claim(
                (int) $context->businessPartnerId,
                $this->partners->authenticatedMobile($context->authenticatedUserId),
                null,
                $this->scope($context),
            );
            $this->audit('Partner referral auto-claim success', $context, 'success');
        } catch (OutOfBoundsException) {
            // No matching mobile lock is a normal direct-registration outcome.
        } catch (DomainException $error) {
            $this->audit('Partner referral auto-claim rejected', $context, 'rejected');
        }
    }

    private function applicable(PartnerContext $context): bool
    {
        return $context->businessPartnerId !== null && in_array($context->moduleCode, self::SUPPORTED_MODULES, true);
    }

    private function scope(PartnerContext $context): string
    {
        return $context->moduleCode === 'RETAIL'
            ? 'RETAIL:' . $context->parentCategoryId
            : $context->moduleCode;
    }

    private function assertApplicable(PartnerContext $context): void
    {
        if (!$this->applicable($context)) {
            throw new ApiException(409, 'REFERRAL_NOT_APPLICABLE', 'Partner identity is not supported by the business referral engine.', 'This referral does not apply to the selected Partner type.');
        }
    }

    private function projection(array $row, bool $claimed): array
    {
        $status = strtoupper((string) ($row['referral_status'] ?? $row['state'] ?? 'REGISTERED'));
        return [
            'claimed' => $claimed && in_array($status, ['REGISTERED', 'ACTIVATED'], true),
            'linked' => in_array($status, ['REGISTERED', 'ACTIVATED'], true),
            'state' => strtolower($status),
            'status' => $status,
            'prefill' => array_filter([
                'businessName' => $row['business_name'] ?? $row['businessName'] ?? null,
                'businessModule' => $row['business_module'] ?? $row['module'] ?? null,
                'retailParentCategory' => $row['category_name'] ?? $row['categoryLabel'] ?? null,
                'locality' => $row['locality'] ?? null,
                'address' => $row['address'] ?? null,
                'pincode' => $row['pincode'] ?? null,
                'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
                'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
                'landmark' => $row['landmark'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    private function terminal(string $state): array
    {
        $state = strtoupper($state);
        $linked = in_array($state, ['REGISTERED', 'ACTIVATED'], true);
        return ['claimed' => $linked, 'linked' => $linked, 'state' => strtolower($state), 'status' => $state, 'prefill' => (object) []];
    }

    private function audit(string $message, PartnerContext $context, string $result): void
    {
        $this->logger?->log('info', $message, [
            'actor_user_public_id' => $context->authenticatedUserPublicId,
            'identity_public_id' => $context->identityPublicId,
            'partner_public_id' => $context->businessPartnerPublicId,
            'result' => $result,
        ]);
    }
}
