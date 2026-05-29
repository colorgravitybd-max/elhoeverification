<?php
/**
 * Stream-export codes to CSV. Honours filters from list.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\CSV;
use Elhoe\Database;
use Elhoe\AuditLog;

$filters = [
    'product_id'   => $_GET['product_id']   ?? '',
    'status'       => $_GET['status']       ?? '',
    'mode'         => $_GET['mode']         ?? '',
    'batch_number' => $_GET['batch_number'] ?? '',
    'high_risk'    => $_GET['high_risk']    ?? '',
    'search'       => $_GET['search']       ?? '',
];

$where = []; $params = [];
if (!empty($filters['product_id']))   { $where[] = 'c.product_id = :pid';   $params[':pid'] = (int) $filters['product_id']; }
if (!empty($filters['status']))       { $where[] = 'c.status = :st';        $params[':st']  = $filters['status']; }
if (!empty($filters['mode']))         { $where[] = 'c.mode = :mode';        $params[':mode'] = $filters['mode']; }
if (!empty($filters['batch_number'])) { $where[] = 'c.batch_number = :bn';  $params[':bn']  = $filters['batch_number']; }
if (!empty($filters['high_risk']))    { $where[] = 'c.risk_score >= :rs';   $params[':rs']  = (int) $filters['high_risk']; }
if (!empty($filters['search']))       { $where[] = '(c.code LIKE :s OR c.batch_number LIKE :s OR p.name LIKE :s)'; $params[':s'] = '%' . $filters['search'] . '%'; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$headers = [
    'serial_code', 'product_id', 'wp_product_id', 'product_sku', 'product_name',
    'batch_number', 'expiry_date', 'mode', 'status', 'scan_count',
    'first_scanned_at', 'first_scan_ip', 'last_scanned_at', 'risk_score',
    'owner_email', 'owner_name', 'registered_at', 'created_at',
];

$gen = function () use ($whereSql, $params) {
    $offset = 0;
    while (true) {
        $rows = Database::all(
            "SELECT c.code AS serial_code, c.product_id, p.wp_product_id, p.sku AS product_sku, p.name AS product_name,
                    c.batch_number, c.expiry_date, c.mode, c.status, c.scan_count,
                    c.first_scanned_at, c.first_scan_ip, c.last_scanned_at, c.risk_score,
                    cu.email AS owner_email, CONCAT_WS(' ', cu.first_name, cu.last_name) AS owner_name, c.registered_at, c.created_at
             FROM codes c
             LEFT JOIN products p ON p.id = c.product_id
             LEFT JOIN customers cu ON cu.id = c.owner_customer_id
             {$whereSql}
             ORDER BY c.id ASC
             LIMIT 1000 OFFSET {$offset}",
            $params
        );
        if (!$rows) break;
        foreach ($rows as $r) yield $r;
        if (count($rows) < 1000) break;
        $offset += 1000;
    }
};

AuditLog::record('codes_export');
CSV::downloadStream('elhoe-codes-' . date('Ymd-His') . '.csv', $headers, $gen());
