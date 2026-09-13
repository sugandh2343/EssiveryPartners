<?php
declare(strict_types=1);

$configured = trim((string) getenv('ESSIVERY_PARTNER_DOCUMENT_STORAGE'));
return [
    'root' => $configured !== '' ? rtrim($configured, '/\\') : dirname(__DIR__) . '/storage/private/documents',
    'max_bytes' => 5 * 1024 * 1024,
    'mime_types' => ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'],
];
