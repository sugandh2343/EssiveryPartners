<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;
use Essivery\Partner\Repositories\PartnerContextRepository;
use Essivery\Partner\Services\PartnerContextService;

final class PartnerContextMiddleware
{
    private array $container;

    public function __construct(array $container)
    {
        $this->container = $container;
    }

    public function handle(Request $request, callable $next): mixed
    {
        $repository = new PartnerContextRepository($this->container['database']->connection());
        $service = new PartnerContextService($repository, $this->container['partner']['identities']);
        try {
            $request->attributes['partnerContext'] = $service->resolve(Auth::context($request), false);
        } catch (ApiException $error) {
            $auth = Auth::context($request);
            if ($error->errorCode === 'PARTNER_CONTEXT_AMBIGUOUS') {
                $this->container['logger']->log('warning', 'Ambiguous Partner ownership rejected', [
                    'request_id' => $request->requestId,
                    'actor_user_public_id' => $auth['user_public_id'],
                    'identity_public_id' => $auth['identity_public_id'],
                    'identity_type' => $auth['identity_type'],
                    'result' => 'rejected',
                ]);
            }
            throw $error;
        }
        return $next($request);
    }
}
