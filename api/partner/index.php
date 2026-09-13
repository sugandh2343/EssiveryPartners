<?php
declare(strict_types=1);

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\ErrorHandler;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Api\Core\Router;

function partnerSharedUserApiPath(): string
{
    $configured = trim((string) getenv('ESSIVERY_USER_API_PATH'));
    return rtrim($configured !== '' ? $configured : dirname(__DIR__) . '/user', '/\\');
}

$sharedApi = partnerSharedUserApiPath();

spl_autoload_register(static function (string $class): void {
    $prefix = 'Essivery\\Partner\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    try {
        require_once $sharedApi . '/core/Env.php';
        require_once $sharedApi . '/core/Cors.php';
        \Essivery\Api\Core\Env::load($sharedApi . '/.env');
        $preflightConfig = require $sharedApi . '/config/app.php';
        $preflightConfig['cors_origins'] = array_values(array_unique(array_merge(
            $preflightConfig['cors_origins'] ?? [],
            ['https://partners.essivery.in'],
            ($preflightConfig['environment'] ?? 'production') === 'development' ? ['http://localhost:5173'] : [],
        )));
        if (!\Essivery\Api\Core\Cors::apply(trim((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), $preflightConfig)) {
            throw new RuntimeException('Origin rejected.');
        }
        header('Access-Control-Expose-Headers: X-Request-ID, Retry-After, X-Idempotent-Replay');
        http_response_code(204);
        exit;
    } catch (Throwable) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => null, 'error' => ['code' => 'ORIGIN_NOT_ALLOWED', 'message' => 'Request origin is not allowed.', 'userMessage' => 'This request is not allowed.', 'fieldErrors' => (object) [], 'retryable' => false, 'metadata' => (object) []], 'meta' => (object) []]);
        exit;
    }
}

try {
    require $sharedApi . '/bootstrap/bootstrap.php';
    $container['config']['cors_origins'] = array_values(array_unique(array_merge(
        $container['config']['cors_origins'] ?? [],
        ['https://partners.essivery.in'],
        ($container['config']['environment'] ?? 'production') === 'development' ? ['http://localhost:5173'] : [],
    )));
    $container['partner'] = require __DIR__ . '/config/partner.php';
    $container['partner']['setup'] = require __DIR__ . '/config/setup.php';
    $container['partner']['documents'] = require __DIR__ . '/config/documents.php';
    $container['partner']['bank'] = require __DIR__ . '/config/bank.php';
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'data' => null, 'error' => ['code' => 'CONFIGURATION_ERROR', 'message' => 'Partner API configuration is incomplete.', 'userMessage' => 'Essivery Partners is temporarily unavailable.', 'fieldErrors' => (object) [], 'retryable' => true, 'metadata' => (object) []], 'meta' => (object) []]);
    exit;
}

try {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $_GET['route'] = preg_replace('#^/api/partner(?=/|$)#', '', $uri) ?: '/';
    $request = Request::capture();
} catch (ApiException $error) {
    Response::error($error, ['requestId' => 'req_' . bin2hex(random_bytes(12))]);
}

$router = new Router($container);
require __DIR__ . '/routes/public.php';
require __DIR__ . '/routes/protected.php';

ErrorHandler::run(static function () use ($router, $request): void {
    header('Access-Control-Expose-Headers: X-Request-ID, Retry-After, X-Idempotent-Replay');
    $router->dispatch($request);
}, $request, $container);
