<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;

final class PartnerHealthController
{
    private const BUILD = '2026.09.13-bank-hotfix.1';

    public function __construct(array $container)
    {
    }

    public function show(Request $request): never
    {
        $bankSecret = (string) getenv('PARTNER_BANK_ENCRYPTION_KEY');
        Response::success([
            'service' => 'essivery-partner-api',
            'status' => 'ok',
            'build' => self::BUILD,
            'readiness' => [
                'openssl' => function_exists('openssl_encrypt') && function_exists('openssl_decrypt'),
                'bankEncryptionConfigured' => strlen($bankSecret) >= 32,
            ],
        ], 200, ['requestId' => $request->requestId]);
    }
}
