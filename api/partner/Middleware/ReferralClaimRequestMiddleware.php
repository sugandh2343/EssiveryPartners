<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class ReferralClaimRequestMiddleware
{
    public function __construct(array $container)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $key = $request->idempotencyKey();
        if ($key === null || !preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'A valid Idempotency-Key header is required.', 'Please retry the referral claim.', ['idempotencyKey' => 'Use 8 to 100 permitted characters.']);
        }
        if (array_keys($request->body) !== ['referralToken']) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Referral claim accepts only referralToken.', 'Remove unsupported referral information.', ['body' => 'Only referralToken is accepted.']);
        }
        $token = trim((string) ($request->body['referralToken'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{40,96}$/', $token)) {
            throw new ApiException(422, 'REFERRAL_INVALID', 'Referral token is invalid.', 'This referral link is not available.');
        }
        return $next($request);
    }
}
