<?php

function jsonResponse(
    bool $success,
    string $message,
    mixed $data = null,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}