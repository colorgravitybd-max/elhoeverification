<?php
/**
 * GET /api/health.json
 * Returns DB connectivity + system uptime info for monitoring tools.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use Elhoe\Database;

api_method('GET');

$status = ['ok' => true, 'time' => date('c'), 'service' => 'elhoe-checker', 'version' => '1.0'];
try {
    $r = Database::scalar('SELECT 1');
    $status['db'] = ($r == 1) ? 'up' : 'unknown';
} catch (\Throwable $e) {
    $status['ok'] = false;
    $status['db'] = 'down';
    api_json($status, 503);
}
api_json($status, 200);
