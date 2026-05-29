<?php
/**
 * Customers VIP export.
 * Query: format=meta|google|full
 *        product_id, from, to, search
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\CustomerService;
use Elhoe\AuditLog;

$format = (string) ($_GET['format'] ?? 'full');
if (!in_array($format, ['meta', 'google', 'full'], true)) {
    $format = 'full';
}

$filters = [
    'product_id' => $_GET['product_id'] ?? '',
    'from'       => $_GET['from']       ?? '',
    'to'         => $_GET['to']         ?? '',
    'search'     => $_GET['search']     ?? '',
];

AuditLog::record('customers_export', 'customer', null, ['format' => $format, 'filters' => $filters]);
CustomerService::streamExport($format, $filters);
