<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class RequireIdempotencyKeyMiddleware
{
    public function __construct(array $container)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $key = $request->idempotencyKey();
        if ($key === null || !preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'A valid Idempotency-Key header is required.', 'Please retry the request.', ['idempotencyKey' => 'Use 8 to 100 letters, numbers, dots, colons, underscores, or hyphens.']);
        }
        return $next($request);
    }
}
