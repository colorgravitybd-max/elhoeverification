<?php
/**
 * Admin > Codes > Master List
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CodeService;
use Elhoe\ProductService;
use Elhoe\CSRF;

Auth::require();

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        flash('error', 'Session expired. Please retry.');
    } else {
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_map('intval', $ids);

        if ($action === 'set_status' && !empty($ids)) {
            $status = (string) ($_POST['set_status_value'] ?? '');
            $n = CodeService::bulkUpdateStatus($ids, $status);
            flash('success', "Updated status on {$n} codes.");
        } elseif ($action === 'delete' && !empty($ids)) {
            $n = CodeService::bulkDelete($ids);
            flash('success', "Deleted {$n} codes.");
        } elseif ($action === 'delete_one') {
            $id = (int) ($_POST['id'] ?? 0);
            if (CodeService::delete($id)) flash('success', 'Code deleted.');
        }
    }
    redirect(admin_url('codes/list.php?' . http_build_query($_GET)));
}

$filters = [
    'product_id'   => $_GET['product_id']   ?? '',
    'status'       => $_GET['status']       ?? '',
    'mode'         => $_GET['mode']         ?? '',
    'batch_number' => $_GET['batch_number'] ?? '',
    'high_risk'    => $_GET['high_risk']    ?? '',
    'search'       => $_GET['search']       ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$total = CodeService::count($filters);
$rows  = CodeService::list($filters, $perPage, ($page - 1) * $perPage);
$pages = max(1, (int) ceil($total / $perPage));
$products = ProductService::list([], 500);

layout_head('Codes', 'codes');
?>

<div class="section-head">
    <h2><?= number_format($total) ?> codes</h2>
    <div class="section-head-actions">
        <a href="<?= e(admin_url('codes/generate.php')) ?>" class="btn btn-primary">+ Generate Codes</a>
        <a href="<?= e(admin_url('codes/import.php')) ?>" class="btn btn-secondary">↑ Import / Paste</a>
        <?php
            $exportQs = http_build_query(array_filter($filters));
        ?>
        <a href="<?= e(api_url('admin/codes-export.php' . ($exportQs ? '?' . $exportQs : ''))) ?>" class="btn btn-secondary">↓ Export CSV</a>
    </div>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>Search</label>
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Code, batch, product…">
    </div>
    <div class="field">
        <label>Product</label>
        <select name="product_id">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= (string) $filters['product_id'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            <option value="active"       <?= $filters['status'] === 'active'       ? 'selected' : '' ?>>Active</option>
            <option value="inactive"     <?= $filters['status'] === 'inactive'     ? 'selected' : '' ?>>Inactive</option>
            <option value="quarantined"  <?= $filters['status'] === 'quarantined'  ? 'selected' : '' ?>>Quarantined</option>
        </select>
    </div>
    <div class="field">
        <label>Mode</label>
        <select name="mode">
            <option value="">All</option>
            <option value="unique"    <?= $filters['mode'] === 'unique'    ? 'selected' : '' ?>>Unique</option>
            <option value="universal" <?= $filters['mode'] === 'universal' ? 'selected' : '' ?>>Universal</option>
        </select>
    </div>
    <div class="field">
        <label>Risk ≥</label>
        <select name="high_risk">
            <option value="">Any</option>
            <option value="50" <?= (string) $filters['high_risk'] === '50' ? 'selected' : '' ?>>50</option>
            <option value="70" <?= (string) $filters['high_risk'] === '70' ? 'selected' : '' ?>>70</option>
            <option value="90" <?= (string) $filters['high_risk'] === '90' ? 'selected' : '' ?>>90</option>
        </select>
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <button class="btn btn-secondary">Filter</button>
    </div>
</form>

<form method="POST" id="bulk-form">
    <?= CSRF::field() ?>

    <div class="card mb-2" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <strong style="font-size:13px">Bulk:</strong>
        <select name="set_status_value" style="width:auto">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="quarantined">Quarantined</option>
        </select>
        <button type="submit" name="action" value="set_status" class="btn btn-secondary btn-sm">Apply Status</button>
        <button type="submit" name="action" value="delete" class="btn btn-sm" style="color:var(--admin-danger)" onclick="return confirm('Delete selected codes? This cannot be undone.')">Delete Selected</button>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" class="bulk-check-all"></th>
                    <th>Code</th>
                    <th>Product</th>
                    <th>Batch</th>
                    <th>Mode</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Scans</th>
                    <th>Risk</th>
                    <th>Owner</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="text-center muted" style="padding:30px">No codes match. <a href="<?= e(admin_url('codes/generate.php')) ?>">Generate</a> or <a href="<?= e(admin_url('codes/import.php')) ?>">import</a> codes.</td></tr>
            <?php else: foreach ($rows as $c):
                $owner = '';
                if (!empty($c['owner_customer_id'])) {
                    $cu = \Elhoe\Database::one("SELECT email, first_name FROM customers WHERE id = :id LIMIT 1", [':id' => $c['owner_customer_id']]);
                    if ($cu) $owner = trim(($cu['first_name'] ?? '') . ' ' . ($cu['email'] ?? ''));
                }
            ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= e($c['id']) ?>" class="bulk-check"></td>
                    <td class="col-mono"><strong><?= e($c['code']) ?></strong></td>
                    <td><?= e($c['product_name'] ?? '—') ?></td>
                    <td class="col-mono"><?= e($c['batch_number'] ?? '—') ?></td>
                    <td><span class="pill pill-<?= e($c['mode']) ?>"><?= e($c['mode']) ?></span></td>
                    <td class="col-mono"><?= e($c['expiry_date'] ?? '—') ?></td>
                    <td><span class="pill pill-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                    <td><?= number_format((int) $c['scan_count']) ?></td>
                    <td>
                        <?php
                        $score = (int) $c['risk_score'];
                        $cls = $score >= 70 ? 'danger' : ($score >= 50 ? 'pill-quarantined' : '');
                        ?>
                        <span style="<?= $score >= 70 ? 'color:var(--admin-danger);font-weight:600' : ($score >= 50 ? 'color:var(--admin-warning);font-weight:600' : 'color:var(--admin-muted)') ?>"><?= $score ?></span>
                    </td>
                    <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($owner ?: '—') ?></td>
                    <td class="col-actions">
                        <a href="<?= e(admin_url('codes/edit.php?id=' . $c['id'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
                    </td>
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
        $to   = min($pages, $page + $window);
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
