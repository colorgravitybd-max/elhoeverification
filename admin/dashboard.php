<?php
/**
 * Admin Dashboard - KPIs + charts.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/_layout.php';

use Elhoe\Auth;
use Elhoe\Database;

Auth::require();

elhoe_start_session();

// KPIs
$totalCodes = (int) Database::scalar("SELECT COUNT(*) FROM codes");
$activeCodes = (int) Database::scalar("SELECT COUNT(*) FROM codes WHERE status = 'active'");
$quarantined = (int) Database::scalar("SELECT COUNT(*) FROM codes WHERE status = 'quarantined'");
$totalProducts = (int) Database::scalar("SELECT COUNT(*) FROM products WHERE status = 'active'");

// Last 30 days
$totalScans = (int) Database::scalar(
    "SELECT COUNT(*) FROM scan_logs WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$failedScans = (int) Database::scalar(
    "SELECT COUNT(*) FROM scan_logs
     WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND result IN ('invalid','quarantined','malformed','rate_limited')"
);
$registrations = (int) Database::scalar(
    "SELECT COUNT(*) FROM customers WHERE registered_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$successRate = $totalScans > 0 ? round((($totalScans - $failedScans) / $totalScans) * 100, 1) : 0;

// Top products by scans (last 30d)
$topProducts = Database::all(
    "SELECT p.name, COUNT(*) AS scans
     FROM scan_logs s
     JOIN products p ON p.id = s.product_id
     WHERE s.scanned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND s.result IN ('valid_universal','valid_unique_first','valid_unique_returning','already_registered')
     GROUP BY p.id
     ORDER BY scans DESC
     LIMIT 5"
);

// Top counterfeit attempts (invalid scans by code attempted)
$topCounterfeits = Database::all(
    "SELECT COALESCE(p.name, 'Unknown product') AS name, COUNT(*) AS attempts
     FROM scan_logs s
     LEFT JOIN products p ON p.id = s.product_id
     WHERE s.scanned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND s.result IN ('invalid','quarantined')
     GROUP BY p.id
     ORDER BY attempts DESC
     LIMIT 5"
);

// Top cities (last 30d)
$topCities = Database::all(
    "SELECT ip_city AS city, ip_country_name AS country, COUNT(*) AS scans
     FROM scan_logs
     WHERE ip_city IS NOT NULL AND ip_city <> ''
     AND scanned_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY ip_city, ip_country_name
     ORDER BY scans DESC
     LIMIT 8"
);

// Daily scan trend for chart (last 14 days)
$trendRows = Database::all(
    "SELECT DATE(scanned_at) AS d, COUNT(*) AS total,
            SUM(CASE WHEN result IN ('valid_universal','valid_unique_first','valid_unique_returning','already_registered') THEN 1 ELSE 0 END) AS ok,
            SUM(CASE WHEN result IN ('invalid','quarantined','malformed') THEN 1 ELSE 0 END) AS fail
     FROM scan_logs
     WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(scanned_at)
     ORDER BY d ASC"
);
$trendLabels = []; $trendOk = []; $trendFail = [];
foreach ($trendRows as $r) {
    $trendLabels[] = (new DateTime($r['d']))->format('M j');
    $trendOk[]     = (int) $r['ok'];
    $trendFail[]   = (int) $r['fail'];
}

// Today hourly activity (BST)
$hourly = Database::all(
    "SELECT HOUR(scanned_at) AS h, COUNT(*) AS c
     FROM scan_logs
     WHERE DATE(scanned_at) = CURDATE()
     GROUP BY HOUR(scanned_at)"
);
$hourlyData = array_fill(0, 24, 0);
foreach ($hourly as $r) {
    $hourlyData[(int) $r['h']] = (int) $r['c'];
}

layout_head('Dashboard', 'dashboard');
?>

<div class="kpis">
    <div class="kpi">
        <div class="kpi-label">Total Scans (30d)</div>
        <div class="kpi-value"><?= number_format($totalScans) ?></div>
        <div class="kpi-sub"><?= number_format($failedScans) ?> failed · <?= $successRate ?>% success</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Registrations (30d)</div>
        <div class="kpi-value"><?= number_format($registrations) ?></div>
        <div class="kpi-sub">VIP customers</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Active Codes</div>
        <div class="kpi-value"><?= number_format($activeCodes) ?></div>
        <div class="kpi-sub"><?= number_format($totalCodes) ?> total · <?= number_format($quarantined) ?> quarantined</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Products</div>
        <div class="kpi-value"><?= number_format($totalProducts) ?></div>
        <div class="kpi-sub">Active in catalog</div>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <h3>14-Day Scan Trend</h3>
        <div class="chart-wrap"><canvas id="chart-trend" height="220"></canvas></div>
    </section>

    <section class="card">
        <h3>Today's Hourly Activity</h3>
        <div class="chart-wrap"><canvas id="chart-hourly" height="220"></canvas></div>
    </section>
</div>

<div class="grid-3">
    <section class="card">
        <h3>Top Verified Products (30d)</h3>
        <?php if (!$topProducts): ?>
            <p class="muted">No data yet.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topProducts as $p): ?>
                    <li>
                        <span class="rank-name"><?= e($p['name']) ?></span>
                        <span class="rank-value"><?= (int) $p['scans'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>Counterfeit Targets (30d)</h3>
        <?php if (!$topCounterfeits): ?>
            <p class="muted">No counterfeit attempts.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topCounterfeits as $p): ?>
                    <li>
                        <span class="rank-name"><?= e($p['name']) ?></span>
                        <span class="rank-value danger"><?= (int) $p['attempts'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="card">
        <h3>Top Cities (30d)</h3>
        <?php if (!$topCities): ?>
            <p class="muted">No location data.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topCities as $c): ?>
                    <li>
                        <span class="rank-name"><?= e($c['city']) ?>, <?= e($c['country'] ?? '') ?></span>
                        <span class="rank-value"><?= (int) $c['scans'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>

<script>
window.DASHBOARD_DATA = {
    trendLabels: <?= json_encode($trendLabels) ?>,
    trendOk:     <?= json_encode($trendOk) ?>,
    trendFail:   <?= json_encode($trendFail) ?>,
    hourly:      <?= json_encode($hourlyData) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script src="<?= e(asset_url('js/dashboard.js')) ?>?v=1" defer></script>

<?php layout_foot(); ?>
