<?php

require_once __DIR__ . '/../bootstrap.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Fetch actual tables related to users / partners / delivery / services
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $matchedTables = array_values(array_filter(
        $allTables,
        function ($table) {
            $name = strtolower($table);

            return
                str_contains($name, 'partner') ||
                str_contains($name, 'user') ||
                str_contains($name, 'delivery') ||
                str_contains($name, 'provider');
        }
    ));

    $result = [];

    foreach ($matchedTables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $result[$table] = $stmt->fetchAll();
    }

    jsonResponse(
        true,
        'Relevant table structures fetched successfully',
        [
            'table_count' => count($matchedTables),
            'tables' => $result,
        ]
    );

} catch (Throwable $e) {

    jsonResponse(
        false,
        'Unable to inspect table structures',
        [
            'error' => $e->getMessage()
        ],
        500
    );
}