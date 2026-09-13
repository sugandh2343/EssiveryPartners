<?php
declare(strict_types=1);

return [
    'identities' => [
        'grocery' => 'RETAIL',
        'vegetable' => 'RETAIL',
        'pharmacy' => 'RETAIL',
        'fashion' => 'RETAIL',
        'electronics' => 'RETAIL',
        'restaurant' => 'RESTAURANT',
        'home_service' => 'HOME_SERVICE',
        'delivery_partner' => 'DELIVERY',
    ],
    'rate_limits' => [
        'public' => ['windowSeconds' => 60, 'maxRequests' => 60],
        'bootstrap' => ['windowSeconds' => 60, 'maxRequests' => 10],
        'authenticated_read' => ['windowSeconds' => 60, 'maxRequests' => 60],
        'normal_write' => ['windowSeconds' => 60, 'maxRequests' => 30],
        'sensitive_write' => ['windowSeconds' => 60, 'maxRequests' => 10],
        'financial' => ['windowSeconds' => 60, 'maxRequests' => 5],
        'upload' => ['windowSeconds' => 60, 'maxRequests' => 10],
    ],
];
