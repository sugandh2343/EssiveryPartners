<?php
declare(strict_types=1);

use Essivery\Partner\Controllers\PartnerHealthController;
use Essivery\Partner\Controllers\PartnerCategoryController;
use Essivery\Partner\Middleware\PartnerRateLimitMiddleware;

$router->get('/health', [PartnerHealthController::class, 'show']);
$router->get('/catalogue/parent-categories', [PartnerCategoryController::class, 'index'], [PartnerRateLimitMiddleware::class]);
