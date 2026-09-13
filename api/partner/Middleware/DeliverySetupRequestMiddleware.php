<?php
declare(strict_types=1);namespace Essivery\Partner\Middleware;
use Essivery\Api\Core\ApiException;use Essivery\Api\Core\Request;
final class DeliverySetupRequestMiddleware{private const ALLOWED=['vehicleType','vehicleRegistrationNumber','vehicleOwnership','confirmed'];public function __construct(array$c){}public function handle(Request$r,callable$n):mixed{$unknown=array_values(array_diff(array_keys($r->body),self::ALLOWED));if($unknown)throw new ApiException(422,'VALIDATION_ERROR','Delivery Partner Setup contains unsupported or protected fields.','Remove unsupported fields.',['body'=>'Unsupported fields: '.implode(', ',$unknown)]);return$n($r);}}

