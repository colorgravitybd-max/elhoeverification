<?php
/**
 * Stream-export all products to CSV.
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\CSV;
use Elhoe\Database;
use Elhoe\AuditLog;

$headers = ['id', 'wp_product_id', 'sku', 'name', 'slug', 'category', 'routine_group', 'image_url', 'product_url', 'description', 'status', 'created_at'];

$gen = function () use ($headers) {
    $offset = 0;
    while (true) {
        $rows = Database::all("SELECT id, wp_product_id, sku, name, slug, category, routine_group, image_url, product_url, description, status, created_at
                               FROM products ORDER BY id ASC LIMIT 1000 OFFSET {$offset}");
        if (!$rows) break;
        foreach ($rows as $r) yield $r;
        if (count($rows) < 1000) break;
        $offset += 1000;
    }
};

AuditLog::record('products_export');
CSV::downloadStream('elhoe-products-' . date('Ymd-His') . '.csv', $headers, $gen());
