<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\Request;
use Essivery\Partner\Policies\PartnerIdentityPolicy;

final class PartnerIdentityMiddleware
{
    private array $container;

    public function __construct(array $container)
    {
        $this->container = $container;
    }

    public function handle(Request $request, callable $next): mixed
    {
        $auth = Auth::context($request);
        $policy = new PartnerIdentityPolicy($this->container['partner']['identities']);
        $request->attributes['partnerIdentity'] = [
            'type' => $auth['identity_type'],
            'module' => $policy->moduleFor($auth['identity_type']),
        ];
        return $next($request);
    }
}
