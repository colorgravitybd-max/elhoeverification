<?php
/**
 * Maintenance cleanup task. Run via Hostinger cron (recommended: daily at 03:00).
 * Cron command:  /usr/bin/php /home/USER/public_html/checker/tools/cleanup.php
 *
 * Cleans expired rate limits and (optionally) very old scan logs / audit log.
 * Safe to run on demand.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

// Only allow CLI execution OR a secret key match for HTTP triggers
$cliMode = (PHP_SAPI === 'cli');
if (!$cliMode) {
    $key = $_GET['key'] ?? '';
    if ($key === '' || $key !== (string) env('APP_KEY', '')) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

use Elhoe\RateLimiter;
use Elhoe\Database;
use Elhoe\Logger;

$report = [];

// 1. Drop expired rate limits older than 24 hours
$report['rate_limits_cleaned'] = RateLimiter::cleanup(24);

// 2. Optional: archive scan logs older than 1 year (set keep_days via env)
$keep = (int) env('SCAN_LOG_RETENTION_DAYS', 365);
if ($keep > 0) {
    $deleted = Database::exec(
        "DELETE FROM scan_logs WHERE scanned_at < DATE_SUB(NOW(), INTERVAL :d DAY)",
        [':d' => $keep]
    )->rowCount();
    $report['scan_logs_purged'] = $deleted;
}

// 3. Audit log: keep 2 years
$report['audit_log_purged'] = Database::exec(
    "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 730 DAY)"
)->rowCount();

// 4. ip_geo_cache: prune entries older than 90 days (we cache 30, but be lazy)
$report['geo_cache_purged'] = Database::exec(
    "DELETE FROM ip_geo_cache WHERE cached_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
)->rowCount();

Logger::info('Cleanup ran', $report);

echo "ELHOE cleanup completed at " . date('c') . "\n";
foreach ($report as $k => $v) {
    echo "  - {$k}: {$v}\n";
}
