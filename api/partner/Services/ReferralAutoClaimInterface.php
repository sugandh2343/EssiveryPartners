<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Partner\Domain\PartnerContext;

interface ReferralAutoClaimInterface
{
    public function afterBootstrap(PartnerContext $context): void;
}
