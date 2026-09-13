<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;

final class PartnerHealthController
{
    public function __construct(array $container)
    {
    }

    public function show(Request $request): never
    {
        Response::success(['service' => 'essivery-partner-api', 'status' => 'ok'], 200, ['requestId' => $request->requestId]);
    }
}
