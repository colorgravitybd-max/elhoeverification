<?php
/**
 * Admin > Products > List
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\ProductService;
use Elhoe\CSRF;

Auth::require();

// Handle delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        flash('error', 'Session expired. Please retry.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            ProductService::delete($id);
            flash('success', 'Product deleted.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect(admin_url('products/list.php'));
}

$filters = [
    'status'   => $_GET['status']   ?? '',
    'category' => $_GET['category'] ?? '',
    'search'   => $_GET['search']   ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$total = ProductService::count($filters);
$rows  = ProductService::list($filters, $perPage, ($page - 1) * $perPage);
$pages = max(1, (int) ceil($total / $perPage));
$categories = ProductService::categories();

layout_head('Products', 'products');
?>

<div class="section-head">
    <h2><?= number_format($total) ?> products</h2>
    <div class="section-head-actions">
        <a href="<?= e(admin_url('products/import.php')) ?>" class="btn btn-secondary">↑ Import CSV</a>
        <a href="<?= e(api_url('admin/products-export.php')) ?>" class="btn btn-secondary">↓ Export CSV</a>
        <a href="<?= e(admin_url('products/edit.php')) ?>" class="btn btn-primary">+ Add Product</a>
    </div>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>Search</label>
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, SKU, slug…">
    </div>
    <div class="field">
        <label>Category</label>
        <select name="category">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            <option value="active"   <?= $filters['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <button class="btn btn-secondary">Filter</button>
    </div>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th></th>
                <th>Name</th>
                <th>SKU</th>
                <th>WP&nbsp;ID</th>
                <th>Category</th>
                <th>Routine</th>
                <th>Status</th>
                <th>Codes</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center muted" style="padding:30px">No products yet. <a href="<?= e(admin_url('products/edit.php')) ?>">Add your first product</a> or <a href="<?= e(admin_url('products/import.php')) ?>">import CSV</a>.</td></tr>
        <?php else: foreach ($rows as $p):
            $codeCount = (int) \Elhoe\Database::scalar("SELECT COUNT(*) FROM codes WHERE product_id = :p", [':p' => $p['id']]);
        ?>
            <tr>
                <td>
                    <?php if ($p['image_url']): ?>
                        <img src="<?= e($p['image_url']) ?>" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:cover">
                    <?php else: ?>
                        <div style="width:36px;height:36px;border-radius:6px;background:var(--admin-cream)"></div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= e($p['name']) ?></strong>
                    <?php if ($p['product_url']): ?>
                        <br><a href="<?= e($p['product_url']) ?>" target="_blank" rel="noopener" style="font-size:11px;color:var(--admin-muted)">View on shop ↗</a>
                    <?php endif; ?>
                </td>
                <td class="col-mono"><?= e($p['sku'] ?? '—') ?></td>
                <td class="col-mono"><?= e($p['wp_product_id'] ?? '—') ?></td>
                <td><?= e($p['category'] ?? '—') ?></td>
                <td><?= e($p['routine_group'] ?? '—') ?></td>
                <td><span class="pill pill-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                <td><?= number_format($codeCount) ?></td>
                <td class="col-actions">
                    <a href="<?= e(admin_url('products/edit.php?id=' . $p['id'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product? Codes referencing it must be removed first.')">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--admin-danger)">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++):
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
