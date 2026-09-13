<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerContextRepository;
use Essivery\Partner\Services\PartnerReferralService;

final class PartnerReferralController
{
    public function __construct(private array $container)
    {
    }

    public function context(Request $request): never
    {
        Response::success($this->service()->context($this->partner($request)), 200, ['requestId' => $request->requestId]);
    }

    public function claim(Request $request): never
    {
        $result = $this->service()->claim($this->partner($request), trim((string) $request->body['referralToken']));
        Response::success($result, 200, ['requestId' => $request->requestId]);
    }

    public function continueWithoutReferral(Request $request): never
    {
        Response::success($this->service()->continueWithoutReferral($this->partner($request)), 200, ['requestId' => $request->requestId]);
    }

    private function service(): PartnerReferralService
    {
        $pdo = $this->container['database']->connection();
        return new PartnerReferralService($pdo, new PartnerContextRepository($pdo), $this->container['logger']);
    }

    private function partner(Request $request): PartnerContext
    {
        return $request->attributes['partnerContext'];
    }
}
