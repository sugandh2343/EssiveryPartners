<?php
declare(strict_types=1);
namespace Essivery\Partner\Middleware;
use Essivery\Api\Core\ApiException;use Essivery\Api\Core\Request;
final class HomeServiceSetupRequestMiddleware{private const ALLOWED=['services','setupPreference','confirmed'];public function __construct(array $config){}public function handle(Request $request,callable $next):mixed{$unknown=array_values(array_diff(array_keys($request->body),self::ALLOWED));if($unknown)throw new ApiException(422,'VALIDATION_ERROR','Home Service Setup contains unsupported or protected fields.','Remove unsupported fields.',['body'=>'Unsupported fields: '.implode(', ',$unknown)]);return$next($request);}}

