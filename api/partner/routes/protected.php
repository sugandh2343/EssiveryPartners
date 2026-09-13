<?php
declare(strict_types=1);

use Essivery\Api\Middleware\AuthMiddleware;
use Essivery\Api\Middleware\IdempotencyMiddleware;
use Essivery\Partner\Controllers\PartnerContextController;
use Essivery\Partner\Controllers\PartnerReferralController;
use Essivery\Partner\Controllers\PartnerSetupController;
use Essivery\Partner\Controllers\PartnerPersonalController;
use Essivery\Partner\Controllers\PartnerBusinessController;
use Essivery\Partner\Controllers\PartnerLocationController;
use Essivery\Partner\Controllers\PartnerBusinessHoursController;
use Essivery\Partner\Controllers\PartnerOperationsController;
use Essivery\Partner\Controllers\PartnerDocumentsController;
use Essivery\Partner\Controllers\PartnerBankController;
use Essivery\Partner\Controllers\PartnerRetailController;
use Essivery\Partner\Controllers\PartnerRestaurantController;
use Essivery\Partner\Controllers\PartnerHomeServiceController;
use Essivery\Partner\Controllers\PartnerDeliveryController;
use Essivery\Partner\Controllers\PartnerReviewController;
use Essivery\Partner\Middleware\PartnerContextMiddleware;
use Essivery\Partner\Middleware\PartnerIdentityMiddleware;
use Essivery\Partner\Middleware\PartnerRateLimitMiddleware;
use Essivery\Partner\Middleware\RequireIdempotencyKeyMiddleware;
use Essivery\Partner\Middleware\EmptyBodyRequestMiddleware;
use Essivery\Partner\Middleware\ReferralClaimRequestMiddleware;
use Essivery\Partner\Middleware\PersonalDetailsRequestMiddleware;
use Essivery\Partner\Middleware\BusinessDetailsRequestMiddleware;
use Essivery\Partner\Middleware\LocationDetailsRequestMiddleware;
use Essivery\Partner\Middleware\BusinessHoursRequestMiddleware;
use Essivery\Partner\Middleware\OperationsRequestMiddleware;
use Essivery\Partner\Middleware\DocumentUploadRequestMiddleware;
use Essivery\Partner\Middleware\BankDetailsRequestMiddleware;
use Essivery\Partner\Middleware\RetailSetupRequestMiddleware;
use Essivery\Partner\Middleware\RestaurantSetupRequestMiddleware;
use Essivery\Partner\Middleware\HomeServiceSetupRequestMiddleware;
use Essivery\Partner\Middleware\DeliverySetupRequestMiddleware;
use Essivery\Partner\Middleware\SetupMutabilityMiddleware;

$identity = [AuthMiddleware::class, PartnerIdentityMiddleware::class];
$context = [...$identity, PartnerRateLimitMiddleware::class, PartnerContextMiddleware::class];

$router->get('/me/context', [PartnerContextController::class, 'show'], $context);
$router->post('/me/bootstrap', [PartnerContextController::class, 'bootstrap'], [
    ...$identity,
    PartnerRateLimitMiddleware::class,
    EmptyBodyRequestMiddleware::class,
    RequireIdempotencyKeyMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/referrals/context', [PartnerReferralController::class, 'context'], $context);
$router->get('/setup', [PartnerSetupController::class, 'show'], $context);
$router->post('/setup/bootstrap', [PartnerSetupController::class, 'bootstrap'], [
    ...$context,
    EmptyBodyRequestMiddleware::class,
    RequireIdempotencyKeyMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/personal', [PartnerPersonalController::class, 'show'], $context);
$router->put('/setup/personal', [PartnerPersonalController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    PersonalDetailsRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/business', [PartnerBusinessController::class, 'show'], $context);
$router->post('/setup/business/description-template', [PartnerBusinessController::class, 'description'], $context);
$router->put('/setup/business', [PartnerBusinessController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    BusinessDetailsRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/location', [PartnerLocationController::class, 'show'], $context);
$router->put('/setup/location', [PartnerLocationController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    LocationDetailsRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/hours', [PartnerBusinessHoursController::class, 'show'], $context);
$router->put('/setup/hours', [PartnerBusinessHoursController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    BusinessHoursRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/operations', [PartnerOperationsController::class, 'show'], $context);
$router->put('/setup/operations', [PartnerOperationsController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    OperationsRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/documents', [PartnerDocumentsController::class, 'show'], $context);
$router->post('/setup/documents', [PartnerDocumentsController::class, 'upload'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    DocumentUploadRequestMiddleware::class,
]);
$router->get('/setup/documents/{publicDocumentId}/file', [PartnerDocumentsController::class, 'file'], $context);
$router->get('/setup/bank', [PartnerBankController::class, 'show'], $context);
$router->post('/setup/bank/proof', [PartnerDocumentsController::class, 'uploadBankProof'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    DocumentUploadRequestMiddleware::class,
]);
$router->put('/setup/bank', [PartnerBankController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    BankDetailsRequestMiddleware::class,
    RequireIdempotencyKeyMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/retail', [PartnerRetailController::class, 'show'], $context);
$router->put('/setup/retail', [PartnerRetailController::class, 'update'], [
    ...$context,
    SetupMutabilityMiddleware::class,
    RetailSetupRequestMiddleware::class,
    RequireIdempotencyKeyMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->get('/setup/restaurant', [PartnerRestaurantController::class, 'show'], $context);
$router->put('/setup/restaurant', [PartnerRestaurantController::class, 'update'], [...$context,SetupMutabilityMiddleware::class,RestaurantSetupRequestMiddleware::class,RequireIdempotencyKeyMiddleware::class,IdempotencyMiddleware::class]);
$router->get('/setup/home-service', [PartnerHomeServiceController::class, 'show'], $context);
$router->put('/setup/home-service', [PartnerHomeServiceController::class, 'update'], [...$context,SetupMutabilityMiddleware::class,HomeServiceSetupRequestMiddleware::class,RequireIdempotencyKeyMiddleware::class,IdempotencyMiddleware::class]);
$router->get('/setup/delivery', [PartnerDeliveryController::class, 'show'], $context);
$router->put('/setup/delivery', [PartnerDeliveryController::class, 'update'], [...$context,SetupMutabilityMiddleware::class,DeliverySetupRequestMiddleware::class,RequireIdempotencyKeyMiddleware::class,IdempotencyMiddleware::class]);
$router->get('/setup/review', [PartnerReviewController::class, 'show'], $context);
$router->post('/setup/submit', [PartnerReviewController::class, 'submit'], [...$context,RequireIdempotencyKeyMiddleware::class,IdempotencyMiddleware::class]);
$router->post('/referrals/claim', [PartnerReferralController::class, 'claim'], [
    ...$context,
    ReferralClaimRequestMiddleware::class,
    IdempotencyMiddleware::class,
]);
$router->post('/referrals/continue-without-referral', [PartnerReferralController::class, 'continueWithoutReferral'], $context);
