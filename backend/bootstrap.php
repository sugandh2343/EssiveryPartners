<?php

$config = require __DIR__ . '/config/env.php';

date_default_timezone_set(
    $config['app']['timezone'] ?? 'Asia/Kolkata'
);

require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/request.php';