<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Partner\Domain\PartnerContext;

final class NullReferralAutoClaim implements ReferralAutoClaimInterface
{
    public function afterBootstrap(PartnerContext $context): void
    {
        // Prompt 2B integration point. Intentionally no referral mutation in 2A.
    }
}
