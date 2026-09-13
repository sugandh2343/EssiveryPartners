<?php

require_once __DIR__ . '/../bootstrap.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->query("
        SELECT *
        FROM parent_categories
        WHERE status = 'active'
        ORDER BY id ASC
    ");

    $rows = $stmt->fetchAll();

    $categories = array_map(function ($row) {

        $name =
            $row['name']
            ?? $row['title']
            ?? $row['category_name']
            ?? 'Partner Category';

        $image =
            $row['image_url']
            ?? $row['image']
            ?? $row['icon_url']
            ?? $row['icon']
            ?? null;

        return [
            'id' => $row['id'],
            'public_id' => $row['public_id'] ?? null,
            'name' => $name,
            'slug' => $row['slug'] ?? null,
            'image' => $image,
            'status' => $row['status'] ?? 'active',
        ];

    }, $rows);

    jsonResponse(
        true,
        'Parent categories fetched successfully',
        $categories
    );

} catch (Throwable $e) {

    jsonResponse(
        false,
        'Unable to fetch parent categories',
        [
            'error' => $e->getMessage()
        ],
        500
    );
}