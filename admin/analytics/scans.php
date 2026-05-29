<?php
/**
 * Admin > Analytics > Scan Log (every verification attempt)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\Database;
use Elhoe\CSRF;
use Elhoe\AuditLog;

Auth::require();

// Bulk delete scans
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        flash('error', 'Session expired.');
    } else {
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_map('intval', $ids);
        if ($action === 'delete' && $ids) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::pdo()->prepare("DELETE FROM scan_logs WHERE id IN ({$place})");
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            AuditLog::record('scan_logs_delete', 'scan_log', null, ['ids' => $ids]);
            flash('success', "Deleted {$n} scan log(s).");
        }
    }
    redirect(admin_url('analytics/scans.php?' . http_build_query($_GET)));
}

$filters = [
    'from'    => $_GET['from']    ?? '',
    'to'      => $_GET['to']      ?? '',
    'result'  => $_GET['result']  ?? '',
    'product' => $_GET['product'] ?? '',
    'search'  => $_GET['search']  ?? '',
];

$where = []; $params = [];
if ($filters['from'])    { $where[] = 's.scanned_at >= :from'; $params[':from'] = $filters['from'] . ' 00:00:00'; }
if ($filters['to'])      { $where[] = 's.scanned_at <= :to';   $params[':to']   = $filters['to']   . ' 23:59:59'; }
if ($filters['result'])  { $where[] = 's.result = :res';       $params[':res']  = $filters['result']; }
if ($filters['product']) { $where[] = 's.product_id = :pid';   $params[':pid']  = (int) $filters['product']; }
if ($filters['search'])  { $where[] = '(s.code_normalized LIKE :s OR s.code_input LIKE :s OR s.ip_address LIKE :s OR s.ip_city LIKE :s)'; $params[':s'] = '%' . $filters['search'] . '%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$total = (int) Database::scalar("SELECT COUNT(*) FROM scan_logs s {$whereSql}", $params);
$rows  = Database::all(
    "SELECT s.*, p.name AS product_name
     FROM scan_logs s LEFT JOIN products p ON p.id = s.product_id
     {$whereSql}
     ORDER BY s.scanned_at DESC
     LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
    $params
);
$pages = max(1, (int) ceil($total / $perPage));

$products = \Elhoe\ProductService::list([], 500);

layout_head('Scan Log', 'analytics');
?>

<div class="tabs">
    <a href="<?= e(admin_url('analytics/overview.php')) ?>">Overview</a>
    <a href="<?= e(admin_url('analytics/scans.php')) ?>" class="is-active">Scan Log</a>
    <a href="<?= e(admin_url('analytics/counterfeit.php')) ?>">Counterfeit Watchlist</a>
</div>

<div class="section-head">
    <h2><?= number_format($total) ?> events</h2>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
        <label>Result</label>
        <select name="result">
            <option value="">All</option>
            <?php foreach (['valid_universal','valid_unique_first','valid_unique_returning','already_registered','invalid','quarantined','malformed','rate_limited'] as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $filters['result'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Product</label>
        <select name="product">
            <option value="">All</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= (string) $filters['product'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Search</label>
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Code, IP, city…">
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <button class="btn btn-secondary">Filter</button>
    </div>
</form>

<form method="POST">
    <?= CSRF::field() ?>

    <div class="card mb-2" style="display:flex;gap:10px;align-items:center">
        <strong style="font-size:13px">Bulk:</strong>
        <button type="submit" name="action" value="delete" class="btn btn-sm" style="color:var(--admin-danger)" onclick="return confirm('Delete selected scan log entries? This cannot be undone.')">Delete Selected Scans</button>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" class="bulk-check-all"></th>
                    <th>Time</th>
                    <th>Code</th>
                    <th>Product</th>
                    <th>IP</th>
                    <th>City</th>
                    <th>Country</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center muted" style="padding:30px">No scan events match.</td></tr>
            <?php else: foreach ($rows as $r):
                $isOk = in_array($r['result'], ['valid_universal','valid_unique_first','valid_unique_returning','already_registered'], true);
            ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= e($r['id']) ?>" class="bulk-check"></td>
                    <td class="col-mono" style="font-size:12px"><?= e(substr((string) $r['scanned_at'], 0, 16)) ?></td>
                    <td class="col-mono">
                        <?php if (!empty($r['code_id'])): ?>
                            <a href="<?= e(admin_url('codes/edit.php?id=' . $r['code_id'])) ?>"><?= e($r['code_normalized']) ?></a>
                        <?php else: ?>
                            <span style="color:var(--admin-danger)"><?= e($r['code_input']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['product_name'] ?? '—') ?></td>
                    <td class="col-mono" style="font-size:12px"><?= e($r['ip_address']) ?></td>
                    <td><?= e($r['ip_city'] ?? '—') ?></td>
                    <td><?= e($r['ip_country_name'] ?? '—') ?></td>
                    <td><span class="pill pill-<?= $isOk ? 'success' : 'fail' ?>"><?= e($r['result']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</form>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php
        $window = 5;
        $from = max(1, $page - $window);
        $to = min($pages, $page + $window);
        for ($i = $from; $i <= $to; $i++):
            $qs = http_build_query(array_merge($_GET, ['page' => $i]));
    ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?<?= e($qs) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>
