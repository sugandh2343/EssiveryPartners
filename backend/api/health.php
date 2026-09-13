<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/response.php';

jsonResponse(
    true,
    'Essivery Partner API is running',
    [
        'service' => 'essivery-partners-api',
        'version' => '1.0.0',
        'timestamp' => date('c'),
    ]
);