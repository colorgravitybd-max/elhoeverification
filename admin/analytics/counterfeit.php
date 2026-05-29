<?php
/**
 * Admin > Analytics > Counterfeit Watchlist
 * Lists codes with elevated risk_score and IPs with abuse patterns.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\Database;

Auth::require();

// High-risk codes
$riskCodes = Database::all(
    "SELECT c.*, p.name AS product_name
     FROM codes c LEFT JOIN products p ON p.id = c.product_id
     WHERE c.risk_score >= 50
     ORDER BY c.risk_score DESC, c.last_scanned_at DESC
     LIMIT 50"
);

// Top abusive IPs (last 7 days)
$abusiveIps = Database::all(
    "SELECT ip_address, ip_country_name, ip_city,
            COUNT(*) AS attempts,
            SUM(CASE WHEN result IN ('invalid','quarantined','malformed') THEN 1 ELSE 0 END) AS fails
     FROM scan_logs
     WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY ip_address, ip_country_name, ip_city
     HAVING fails >= 3
     ORDER BY fails DESC, attempts DESC
     LIMIT 30"
);

// Codes scanned in many countries (suspicious geo spread)
$geoSpread = Database::all(
    "SELECT c.id, c.code, c.scan_count, p.name AS product_name,
            COUNT(DISTINCT s.ip_country) AS countries
     FROM codes c
     LEFT JOIN products p ON p.id = c.product_id
     LEFT JOIN scan_logs s ON s.code_id = c.id
     WHERE c.mode = 'unique' AND c.scan_count > 1
     GROUP BY c.id
     HAVING countries > 2
     ORDER BY countries DESC LIMIT 20"
);

layout_head('Counterfeit Watchlist', 'analytics');
?>

<div class="tabs">
    <a href="<?= e(admin_url('analytics/overview.php')) ?>">Overview</a>
    <a href="<?= e(admin_url('analytics/scans.php')) ?>">Scan Log</a>
    <a href="<?= e(admin_url('analytics/counterfeit.php')) ?>" class="is-active">Counterfeit Watchlist</a>
</div>

<section class="card mb-2">
    <h3>⚠ High-Risk Codes (score ≥ 50)</h3>
    <p class="muted" style="margin:0 0 10px;font-size:12px">Codes auto-quarantine at score 70+. Review manually below 70.</p>
    <?php if (!$riskCodes): ?>
        <p class="muted">No high-risk codes. ✓</p>
    <?php else: ?>
        <div class="table-wrap" style="margin:0">
            <table class="data">
                <thead>
                    <tr><th>Code</th><th>Product</th><th>Mode</th><th>Status</th><th>Risk</th><th>Scans</th><th>Last Scan</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($riskCodes as $c): ?>
                    <tr>
                        <td class="col-mono"><strong><?= e($c['code']) ?></strong></td>
                        <td><?= e($c['product_name'] ?? '—') ?></td>
                        <td><span class="pill pill-<?= e($c['mode']) ?>"><?= e($c['mode']) ?></span></td>
                        <td><span class="pill pill-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                        <td>
                            <strong style="<?= ((int) $c['risk_score']) >= 70 ? 'color:var(--admin-danger)' : 'color:var(--admin-warning)' ?>"><?= (int) $c['risk_score'] ?>/100</strong>
                        </td>
                        <td><?= number_format((int) $c['scan_count']) ?></td>
                        <td class="col-mono" style="font-size:12px"><?= e(substr((string) ($c['last_scanned_at'] ?? '—'), 0, 16)) ?></td>
                        <td class="col-actions"><a href="<?= e(admin_url('codes/edit.php?id=' . $c['id'])) ?>" class="btn btn-ghost btn-sm">Inspect</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card mb-2">
    <h3>🚨 Abusive IPs (7 days, ≥3 fails)</h3>
    <?php if (!$abusiveIps): ?>
        <p class="muted">No abusive IP patterns detected. ✓</p>
    <?php else: ?>
        <div class="table-wrap" style="margin:0">
            <table class="data">
                <thead>
                    <tr><th>IP</th><th>City</th><th>Country</th><th>Failed Scans</th><th>Total Attempts</th></tr>
                </thead>
                <tbody>
                <?php foreach ($abusiveIps as $r): ?>
                    <tr>
                        <td class="col-mono"><?= e($r['ip_address']) ?></td>
                        <td><?= e($r['ip_city'] ?? '—') ?></td>
                        <td><?= e($r['ip_country_name'] ?? '—') ?></td>
                        <td><strong style="color:var(--admin-danger)"><?= (int) $r['fails'] ?></strong></td>
                        <td><?= (int) $r['attempts'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h3>🌍 Unique Codes Scanned in 3+ Countries</h3>
    <p class="muted" style="margin:0 0 10px;font-size:12px">Strong counterfeit signal — a unique code can't legitimately appear across multiple countries.</p>
    <?php if (!$geoSpread): ?>
        <p class="muted">No suspicious geographic spread. ✓</p>
    <?php else: ?>
        <div class="table-wrap" style="margin:0">
            <table class="data">
                <thead>
                    <tr><th>Code</th><th>Product</th><th>Countries</th><th>Scans</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($geoSpread as $c): ?>
                    <tr>
                        <td class="col-mono"><strong><?= e($c['code']) ?></strong></td>
                        <td><?= e($c['product_name'] ?? '—') ?></td>
                        <td><strong style="color:var(--admin-danger)"><?= (int) $c['countries'] ?></strong></td>
                        <td><?= (int) $c['scan_count'] ?></td>
                        <td class="col-actions"><a href="<?= e(admin_url('codes/edit.php?id=' . $c['id'])) ?>" class="btn btn-ghost btn-sm">Inspect</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php layout_foot(); ?>
