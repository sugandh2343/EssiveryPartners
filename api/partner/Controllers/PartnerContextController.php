<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Repositories\PartnerContextRepository;
use Essivery\Partner\Services\PartnerBootstrapService;
use Essivery\Partner\Services\PartnerContextService;
use Essivery\Partner\Services\PartnerReferralService;

final class PartnerContextController
{
    private array $container;

    public function __construct(array $container)
    {
        $this->container = $container;
    }

    public function show(Request $request): never
    {
        /** @var PartnerContext $context */
        $context = $request->attributes['partnerContext'];
        Response::success($context->publicPayload(), 200, ['requestId' => $request->requestId]);
    }

    public function bootstrap(Request $request): never
    {
        $pdo = $this->container['database']->connection();
        $repository = new PartnerContextRepository($pdo);
        $contexts = new PartnerContextService($repository, $this->container['partner']['identities']);
        $referrals = new PartnerReferralService($pdo, $repository, $this->container['logger']);
        $service = new PartnerBootstrapService($pdo, $repository, $contexts, $referrals);
        try {
            $context = $service->bootstrap(Auth::context($request));
        } catch (ApiException $error) {
            $auth = Auth::context($request);
            $this->container['logger']->log('warning', 'Partner bootstrap rejected', [
                'request_id' => $request->requestId,
                'actor_user_public_id' => $auth['user_public_id'],
                'identity_public_id' => $auth['identity_public_id'],
                'identity_type' => $auth['identity_type'],
                'result' => 'rejected',
                'error_code' => $error->errorCode,
            ]);
            throw $error;
        }
        $this->container['logger']->log('info', 'Partner bootstrap completed', [
            'request_id' => $request->requestId,
            'actor_user_public_id' => $context->authenticatedUserPublicId,
            'identity_public_id' => $context->identityPublicId,
            'identity_type' => $context->identityType,
            'partner_public_id' => $context->businessPartnerPublicId ?? $context->deliveryPartnerPublicId,
            'result' => 'success',
        ]);
        Response::success($context->publicPayload(), 200, ['requestId' => $request->requestId]);
    }
}
