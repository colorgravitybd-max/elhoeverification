<?php
/**
 * Admin > Analytics > Overview (filterable date range)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\Database;

Auth::require();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$fromDt = $from . ' 00:00:00';
$toDt   = $to   . ' 23:59:59';

$totalScans = (int) Database::scalar(
    "SELECT COUNT(*) FROM scan_logs WHERE scanned_at BETWEEN :a AND :b",
    [':a' => $fromDt, ':b' => $toDt]
);
$failedScans = (int) Database::scalar(
    "SELECT COUNT(*) FROM scan_logs WHERE scanned_at BETWEEN :a AND :b
     AND result IN ('invalid','quarantined','malformed','rate_limited')",
    [':a' => $fromDt, ':b' => $toDt]
);
$registrations = (int) Database::scalar(
    "SELECT COUNT(*) FROM customers WHERE registered_at BETWEEN :a AND :b",
    [':a' => $fromDt, ':b' => $toDt]
);
$success = $totalScans - $failedScans;
$rate = $totalScans > 0 ? round(($success / $totalScans) * 100, 1) : 0;

// By product
$byProduct = Database::all(
    "SELECT COALESCE(p.name, 'Unknown') AS name, COUNT(*) AS scans
     FROM scan_logs s LEFT JOIN products p ON p.id = s.product_id
     WHERE s.scanned_at BETWEEN :a AND :b
     AND s.result IN ('valid_universal','valid_unique_first','valid_unique_returning','already_registered')
     GROUP BY p.id ORDER BY scans DESC LIMIT 10",
    [':a' => $fromDt, ':b' => $toDt]
);

// Daily trend
$daily = Database::all(
    "SELECT DATE(scanned_at) AS d,
            SUM(CASE WHEN result IN ('valid_universal','valid_unique_first','valid_unique_returning','already_registered') THEN 1 ELSE 0 END) AS ok,
            SUM(CASE WHEN result IN ('invalid','quarantined','malformed') THEN 1 ELSE 0 END) AS fail
     FROM scan_logs WHERE scanned_at BETWEEN :a AND :b
     GROUP BY DATE(scanned_at) ORDER BY d ASC",
    [':a' => $fromDt, ':b' => $toDt]
);
$labels = []; $okSeries = []; $failSeries = [];
foreach ($daily as $r) {
    $labels[] = (new DateTime($r['d']))->format('M j');
    $okSeries[] = (int) $r['ok'];
    $failSeries[] = (int) $r['fail'];
}

// Geo
$cities = Database::all(
    "SELECT ip_city AS city, ip_country_name AS country, COUNT(*) AS scans
     FROM scan_logs WHERE ip_city IS NOT NULL AND ip_city <> ''
     AND scanned_at BETWEEN :a AND :b
     GROUP BY ip_city, ip_country_name ORDER BY scans DESC LIMIT 10",
    [':a' => $fromDt, ':b' => $toDt]
);
$countries = Database::all(
    "SELECT ip_country_name AS country, COUNT(*) AS scans
     FROM scan_logs WHERE ip_country_name IS NOT NULL AND ip_country_name <> ''
     AND scanned_at BETWEEN :a AND :b
     GROUP BY ip_country_name ORDER BY scans DESC LIMIT 10",
    [':a' => $fromDt, ':b' => $toDt]
);

// Top counterfeit attempts (raw inputs)
$counterfeit = Database::all(
    "SELECT COALESCE(p.name, '— Unknown —') AS name, s.code_normalized AS code, COUNT(*) AS attempts
     FROM scan_logs s LEFT JOIN products p ON p.id = s.product_id
     WHERE s.scanned_at BETWEEN :a AND :b AND s.result IN ('invalid','quarantined')
     GROUP BY p.id, s.code_normalized ORDER BY attempts DESC LIMIT 10",
    [':a' => $fromDt, ':b' => $toDt]
);

layout_head('Analytics', 'analytics');
?>

<div class="tabs">
    <a href="<?= e(admin_url('analytics/overview.php')) ?>" class="is-active">Overview</a>
    <a href="<?= e(admin_url('analytics/scans.php')) ?>">Scan Log</a>
    <a href="<?= e(admin_url('analytics/counterfeit.php')) ?>">Counterfeit Watchlist</a>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <button class="btn btn-secondary">Apply Filter</button>
    </div>
</form>

<div class="kpis">
    <div class="kpi"><div class="kpi-label">Total Scans</div><div class="kpi-value"><?= number_format($totalScans) ?></div></div>
    <div class="kpi"><div class="kpi-label">Successful</div><div class="kpi-value"><?= number_format($success) ?></div><div class="kpi-sub"><?= $rate ?>% rate</div></div>
    <div class="kpi"><div class="kpi-label">Failed / Counterfeit</div><div class="kpi-value" style="color:var(--admin-danger)"><?= number_format($failedScans) ?></div></div>
    <div class="kpi"><div class="kpi-label">New Registrations</div><div class="kpi-value"><?= number_format($registrations) ?></div></div>
</div>

<div class="grid-2">
    <section class="card">
        <h3>Daily Scan Trend</h3>
        <div class="chart-wrap"><canvas id="chart-daily" height="220"></canvas></div>
    </section>
    <section class="card">
        <h3>Scans by Product (top 10)</h3>
        <div class="chart-wrap"><canvas id="chart-products" height="220"></canvas></div>
    </section>
</div>

<div class="grid-3">
    <section class="card">
        <h3>Top Cities</h3>
        <?php if (!$cities): ?><p class="muted">No location data yet.</p><?php else: ?>
        <ol class="rank-list">
            <?php foreach ($cities as $c): ?>
                <li><span class="rank-name"><?= e($c['city']) ?>, <?= e($c['country']) ?></span><span class="rank-value"><?= (int) $c['scans'] ?></span></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
    <section class="card">
        <h3>Top Countries</h3>
        <?php if (!$countries): ?><p class="muted">No data.</p><?php else: ?>
        <ol class="rank-list">
            <?php foreach ($countries as $c): ?>
                <li><span class="rank-name"><?= e($c['country']) ?></span><span class="rank-value"><?= (int) $c['scans'] ?></span></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
    <section class="card">
        <h3>Counterfeit Attempts</h3>
        <?php if (!$counterfeit): ?><p class="muted">No counterfeit attempts in this range.</p><?php else: ?>
        <ol class="rank-list">
            <?php foreach ($counterfeit as $cf): ?>
                <li><span class="rank-name"><?= e($cf['name']) ?> <code style="font-size:11px;color:var(--admin-muted)"><?= e($cf['code']) ?></code></span><span class="rank-value danger"><?= (int) $cf['attempts'] ?></span></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
</div>

<script>
window.OVERVIEW_DATA = {
    labels: <?= json_encode($labels) ?>,
    ok:     <?= json_encode($okSeries) ?>,
    fail:   <?= json_encode($failSeries) ?>,
    productLabels: <?= json_encode(array_column($byProduct, 'name')) ?>,
    productData:   <?= json_encode(array_map('intval', array_column($byProduct, 'scans'))) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var t1 = setInterval(function () {
        if (!window.Chart) return;
        clearInterval(t1);
        var D = window.OVERVIEW_DATA;
        var primary = '#3E5641', danger = '#A94442', accent = '#A4B494';
        new Chart(document.getElementById('chart-daily'), {
            type: 'line',
            data: { labels: D.labels, datasets: [
                { label: 'Successful', data: D.ok,   borderColor: primary, backgroundColor: 'rgba(62,86,65,.12)', tension: .35, fill: true },
                { label: 'Failed',     data: D.fail, borderColor: danger,  backgroundColor: 'rgba(169,68,66,.12)', tension: .35, fill: true }
            ]},
            options: { responsive:true, maintainAspectRatio:false, scales:{x:{grid:{display:false}}, y:{beginAtZero:true,ticks:{precision:0}}} }
        });
        new Chart(document.getElementById('chart-products'), {
            type: 'bar',
            data: { labels: D.productLabels, datasets: [{ label: 'Scans', data: D.productData, backgroundColor: accent, borderRadius: 4 }] },
            options: { responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,ticks:{precision:0}}} }
        });
    }, 80);
});
</script>

<?php layout_foot(); ?>
