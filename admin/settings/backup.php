<?php
/**
 * Admin > Settings > Backup (DB dump download)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\Database;

Auth::requireRole('super_admin');

$dbName = (string) env('DB_NAME', '');
$tableCount = (int) Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :s", [':s' => $dbName]);
$totals = [
    'products'   => (int) Database::scalar("SELECT COUNT(*) FROM products"),
    'codes'      => (int) Database::scalar("SELECT COUNT(*) FROM codes"),
    'scan_logs'  => (int) Database::scalar("SELECT COUNT(*) FROM scan_logs"),
    'customers'  => (int) Database::scalar("SELECT COUNT(*) FROM customers"),
    'audit_log'  => (int) Database::scalar("SELECT COUNT(*) FROM audit_log"),
];

layout_head('Backup', 'settings');
?>

<div class="tabs">
    <a href="<?= e(admin_url('settings/general.php')) ?>">General</a>
    <a href="<?= e(admin_url('settings/email.php')) ?>">Email</a>
    <a href="<?= e(admin_url('settings/admins.php')) ?>">Admins</a>
    <a href="<?= e(admin_url('settings/backup.php')) ?>" class="is-active">Backup</a>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Database Backup</h2>
        <p class="muted">Download a full SQL dump of all tables. Useful for periodic off-site backups or before risky operations.</p>

        <ul style="font-size:13px">
            <li><strong>Database:</strong> <code><?= e($dbName) ?></code></li>
            <li><strong>Tables:</strong> <?= $tableCount ?></li>
            <li><strong>Products:</strong> <?= number_format($totals['products']) ?></li>
            <li><strong>Codes:</strong> <?= number_format($totals['codes']) ?></li>
            <li><strong>Scan Logs:</strong> <?= number_format($totals['scan_logs']) ?></li>
            <li><strong>Customers:</strong> <?= number_format($totals['customers']) ?></li>
            <li><strong>Audit Log:</strong> <?= number_format($totals['audit_log']) ?></li>
        </ul>

        <a href="<?= e(api_url('admin/backup-download.php')) ?>" class="btn btn-primary">↓ Download SQL Backup</a>
    </div>

    <div class="card muted">
        <h3>Recommended Schedule</h3>
        <p style="font-size:13px">Hostinger hPanel includes automatic daily backups. This download is for ad-hoc / off-site copies.</p>
        <h3 style="margin-top:14px">Restore</h3>
        <p style="font-size:13px">To restore: hPanel → MySQL Databases → phpMyAdmin → Import → upload the .sql file.</p>
        <h3 style="margin-top:14px">Cleanup</h3>
        <p style="font-size:13px">Old <code>scan_logs</code> &gt; 1 year can be safely archived. Old <code>rate_limits</code> &gt; 24h are auto-cleaned.</p>
    </div>
</div>

<?php layout_foot(); ?>
