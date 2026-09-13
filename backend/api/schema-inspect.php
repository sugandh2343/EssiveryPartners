<?php

require_once __DIR__ . '/../bootstrap.php';

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->query('SHOW TABLES');

    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse(
        true,
        'Database schema fetched successfully',
        [
            'table_count' => count($tables),
            'tables' => $tables,
        ]
    );

} catch (Throwable $e) {

    jsonResponse(
        false,
        'Unable to inspect database schema',
        [
            'error' => $e->getMessage()
        ],
        500
    );
}