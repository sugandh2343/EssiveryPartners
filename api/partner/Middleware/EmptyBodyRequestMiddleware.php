<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class EmptyBodyRequestMiddleware
{
    private const BOOTSTRAP_PATHS = ['/me/bootstrap', '/setup/bootstrap'];

    public function __construct(array $container)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        // Defense in depth: never reject a valid mutation body if an older or
        // cached route definition accidentally includes this middleware.
        if (!in_array($request->path, self::BOOTSTRAP_PATHS, true)) {
            return $next($request);
        }
        if ($request->body !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Bootstrap does not accept request fields.', 'Remove unsupported information and try again.', ['body' => 'The bootstrap request body must be empty.']);
        }
        return $next($request);
    }
}
