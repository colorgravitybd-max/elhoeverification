<?php
/**
 * Download a starter CSV template for product imports.
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\CSV;

$headers = ['wp_product_id', 'sku', 'name', 'slug', 'category', 'routine_group', 'image_url', 'product_url', 'description', 'status'];
$rows = [
    [
        'wp_product_id' => 2387,
        'sku'           => 'ELH-LS-001',
        'name'          => 'ELHOE Lightening Serum',
        'slug'          => '',
        'category'      => 'Serums',
        'routine_group' => 'Brightening',
        'image_url'     => 'https://elhoe.com/wp-content/uploads/lightening.jpg',
        'product_url'   => 'https://elhoe.com/product/elhoe-lightening-serum/',
        'description'   => 'A natural brightening serum.',
        'status'        => 'active',
    ],
];

CSV::downloadStream('elhoe-products-template.csv', $headers, $rows);
