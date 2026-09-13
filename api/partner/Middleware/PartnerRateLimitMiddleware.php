<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class PartnerRateLimitMiddleware
{
    private array $container;

    public function __construct(array $container)
    {
        $this->container = $container;
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (empty($this->container['config']['rate_limit_enabled'])) return $next($request);
        $category = in_array($request->path, ['/me/bootstrap', '/setup/bootstrap'], true)
            ? 'bootstrap'
            : ($request->path === '/catalogue/parent-categories' ? 'public' : 'authenticated_read');
        $rule = $this->container['partner']['rate_limits'][$category];
        $auth = $request->attributes['auth'] ?? [];
        $identity = $request->attributes['partnerIdentity'] ?? [];
        $routeKey = 'partner:' . $category . ':' . $request->method . ':' . $request->path;
        $bucket = hash('sha256', implode('|', [
            $routeKey,
            $request->clientIp,
            (string) ($auth['user_public_id'] ?? 'anonymous'),
            (string) ($auth['identity_public_id'] ?? 'none'),
            (string) ($identity['type'] ?? 'none'),
        ]));
        $pdo = $this->container['database']->connection();
        $select = $pdo->prepare("SELECT id,request_count,TIMESTAMPDIFF(SECOND,window_started_at,UTC_TIMESTAMP()) age_seconds FROM api_rate_limits WHERE bucket_hash=:bucket AND status='active' LIMIT 1");
        $select->execute(['bucket' => $bucket]);
        $row = $select->fetch();
        $window = max(1, (int) $rule['windowSeconds']);
        $maximum = max(1, (int) $rule['maxRequests']);
        if (!$row) {
            $insert = $pdo->prepare("INSERT INTO api_rate_limits(public_id,bucket_hash,route_key,request_count,window_started_at,expires_at,status,created_at,updated_at) VALUES(:public,:bucket,:route,1,UTC_TIMESTAMP(),:expires,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
            $insert->execute(['public' => 'RATE_' . bin2hex(random_bytes(10)), 'bucket' => $bucket, 'route' => $routeKey, 'expires' => gmdate('Y-m-d H:i:s', time() + $window)]);
            return $next($request);
        }
        if ((int) $row['age_seconds'] >= $window) {
            $reset = $pdo->prepare('UPDATE api_rate_limits SET request_count=1,window_started_at=UTC_TIMESTAMP(),expires_at=:expires,updated_at=UTC_TIMESTAMP() WHERE id=:id');
            $reset->execute(['expires' => gmdate('Y-m-d H:i:s', time() + $window), 'id' => $row['id']]);
            return $next($request);
        }
        if ((int) $row['request_count'] >= $maximum) {
            $retryAfter = max(1, $window - (int) $row['age_seconds']);
            throw new ApiException(429, 'RATE_LIMITED', 'Too many Partner API requests.', 'Please wait before trying again.', [], true, ['retryAfter' => $retryAfter]);
        }
        $pdo->prepare('UPDATE api_rate_limits SET request_count=request_count+1,updated_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $row['id']]);
        return $next($request);
    }
}
