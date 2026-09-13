<?php
declare(strict_types=1);

namespace Essivery\Partner\Controllers;

use Essivery\Api\Core\Request;
use Essivery\Api\Core\Response;
use PDO;

final class PartnerCategoryController
{
    private PDO $pdo;

    public function __construct(array $container)
    {
        $this->pdo = $container['database']->connection();
    }

    public function index(Request $request): never
    {
        $statement = $this->pdo->query(
            "SELECT id,public_id,name,slug,icon_url
             FROM parent_categories
             WHERE status='active' AND deleted_at IS NULL
             ORDER BY display_order,name"
        );
        $categories = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'image' => $row['icon_url'] ?: null,
        ], $statement->fetchAll());

        Response::success($categories, 200, ['requestId' => $request->requestId]);
    }
}
