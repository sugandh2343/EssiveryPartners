<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;
use Essivery\Partner\Domain\PartnerContext;

final class OperationsRequestMiddleware
{
    public function __construct(array $container) {}

    public function handle(Request $request, callable $next): mixed
    {
        /** @var PartnerContext|null $context */
        $context = $request->attributes['partnerContext'] ?? null;
        if (!$context || $context->deliveryPartnerId !== null) {
            throw new ApiException(409, 'PARTNER_SETUP_NOT_APPLICABLE', 'Operations does not apply to Delivery Partners.', 'Operations is not required for this Partner type.');
        }
        $allowed = $context->moduleCode === 'HOME_SERVICE'
            ? ['serviceAtCustomerLocation']
            : ['essiveryDelivery','selfDelivery','customerPickup'];
        $unknown = array_values(array_diff(array_keys($request->body), $allowed));
        if ($unknown) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Operations contains unsupported or protected fields.', 'Remove unsupported Operations fields.', [
                'body' => 'Unsupported fields: ' . implode(', ', $unknown),
            ]);
        }
        $key = $request->idempotencyKey();
        if ($key === null || !preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'A valid Idempotency-Key header is required.', 'Please retry saving Operations.', [
                'idempotencyKey' => 'Use 8 to 100 permitted characters.',
            ]);
        }
        return $next($request);
    }
}
