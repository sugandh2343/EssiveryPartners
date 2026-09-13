<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Auth;
use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use Essivery\Api\Services\AuditService;
use Essivery\Partner\Domain\PartnerContext;
use Essivery\Partner\Services\PartnerSetupService;

final class PartnerSetupController
{
    public function __construct(private array $container)
    {
    }

    public function show(Request $request): never
    {
        Response::success($this->service()->load($this->context($request)), 200, ['requestId' => $request->requestId]);
    }

    public function bootstrap(Request $request): never
    {
        $context = $this->context($request);
        $result = $this->service()->bootstrap($context);
        if ($result['created'] || $result['initializedSteps'] > 0) {
            $auth = Auth::context($request);
            (new AuditService($this->container['database']->connection()))->record(
                (int) $auth['user_id'],
                (int) $auth['identity_id'],
                $result['created'] ? 'partner_setup.application_created' : 'partner_setup.steps_initialized',
                'partner_setup_application',
                null,
                $request->requestId,
                ['module' => $context->moduleCode, 'steps_initialized' => $result['initializedSteps']]
            );
        }
        Response::success($result['summary'], 200, ['requestId' => $request->requestId]);
    }

    private function service(): PartnerSetupService
    {
        $definitions = $this->container['partner']['setup'] ?? null;
        if (!is_array($definitions)) {
            $configFile = dirname(__DIR__) . '/config/setup.php';
            if (is_file($configFile)) $definitions = require $configFile;
        }
        if (!is_array($definitions) || $definitions === []) {
            throw new ApiException(503, 'PARTNER_SETUP_CONFIGURATION_ERROR', 'Partner setup definitions are unavailable.', 'Business Setup is temporarily unavailable.', [], true);
        }
        return new PartnerSetupService($this->container['database']->connection(), $definitions);
    }

    private function context(Request $request): PartnerContext
    {
        return $request->attributes['partnerContext'];
    }
}
