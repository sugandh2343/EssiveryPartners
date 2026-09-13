<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class LocationDetailsRequestMiddleware
{
    private const ALLOWED=['addressLine1','addressLine2','locality','landmark','pincode','city','state','latitude','longitude','mapConfirmed'];
    public function __construct(array $container) {}
    public function handle(Request$request,callable$next):mixed
    {
        $unknown=array_values(array_diff(array_keys($request->body),self::ALLOWED));
        if($unknown)throw new ApiException(422,'VALIDATION_ERROR','Location contains unsupported or protected fields.','Remove unsupported Location fields.',['body'=>'Unsupported fields: '.implode(', ',$unknown)]);
        $key=$request->idempotencyKey();
        if($key===null||!preg_match('/^[A-Za-z0-9._:-]{8,100}$/',$key))throw new ApiException(422,'VALIDATION_ERROR','A valid Idempotency-Key header is required.','Please retry saving Location.',['idempotencyKey'=>'Use 8 to 100 permitted characters.']);
        return$next($request);
    }
}
