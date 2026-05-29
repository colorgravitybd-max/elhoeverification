<?php
/**
 * Streams a mysqldump-equivalent SQL dump of all tables.
 * Pure-PHP (no shell calls) so it works on shared hosting.
 */
declare(strict_types=1);
require_once __DIR__ . '/_admin_bootstrap.php';

use Elhoe\Database;
use Elhoe\Auth;
use Elhoe\AuditLog;

Auth::requireRole('super_admin');

$pdo = Database::pdo();

$dbName = (string) env('DB_NAME', 'elhoe');
$filename = 'elhoe-backup-' . date('Ymd-His') . '.sql';

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/sql; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

fwrite($out, "-- ELHOE Verification Backup\n");
fwrite($out, "-- Generated: " . date('c') . "\n");
fwrite($out, "-- Database: {$dbName}\n");
fwrite($out, "SET NAMES utf8mb4;\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

// List tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN, 0);

foreach ($tables as $table) {
    // CREATE TABLE
    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    $createSql = $row['Create Table'] ?? null;
    if (!$createSql) continue;

    fwrite($out, "\n-- ----------------------------\n");
    fwrite($out, "-- Table: `{$table}`\n");
    fwrite($out, "-- ----------------------------\n");
    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($out, $createSql . ";\n\n");

    // Data
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($count === 0) continue;

    $batch = 500;
    for ($offset = 0; $offset < $count; $offset += $batch) {
        $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$batch} OFFSET {$offset}")->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) break;

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';

        fwrite($out, "INSERT INTO `{$table}` ({$cols}) VALUES\n");
        $values = [];
        foreach ($rows as $r) {
            $vals = [];
            foreach ($r as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($v) || is_float($v)) {
                    $vals[] = (string) $v;
                } else {
                    $vals[] = $pdo->quote((string) $v);
                }
            }
            $values[] = '(' . implode(', ', $vals) . ')';
        }
        fwrite($out, implode(",\n", $values) . ";\n");

        if (function_exists('flush')) @flush();
    }
}

fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);

AuditLog::record('backup_download');
exit;
