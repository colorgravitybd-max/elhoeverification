<?php
/**
 * Download starter CSV template for code imports.
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\CSV;

$headers = ['serial_code', 'wp_product_id', 'product_sku', 'batch_number', 'expiry_date', 'mode', 'status', 'scan_count'];
$rows = [
    [
        'serial_code'   => '72987923492',
        'wp_product_id' => 2387,
        'product_sku'   => '',
        'batch_number'  => 'BATCH-02-26-627',
        'expiry_date'   => '2028-05-27',
        'mode'          => 'universal',
        'status'        => 'active',
        'scan_count'    => 0,
    ],
];

CSV::downloadStream('elhoe-codes-template.csv', $headers, $rows);
