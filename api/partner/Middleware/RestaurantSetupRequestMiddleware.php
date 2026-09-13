<?php
declare(strict_types=1);
namespace Essivery\Partner\Middleware;
use Essivery\Api\Core\ApiException;use Essivery\Api\Core\Request;
final class RestaurantSetupRequestMiddleware{private const ALLOWED=['cuisines','menuSetupMode','confirmed'];public function __construct(array$c){}public function handle(Request$r,callable$n):mixed{$u=array_values(array_diff(array_keys($r->body),self::ALLOWED));if($u)throw new ApiException(422,'VALIDATION_ERROR','Restaurant Setup contains unsupported or protected fields.','Remove unsupported fields.',['body'=>'Unsupported fields: '.implode(', ',$u)]);return$n($r);}}
