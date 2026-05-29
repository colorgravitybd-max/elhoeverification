<?php
/**
 * Admin > Customers > List + VIP Export
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CustomerService;
use Elhoe\ProductService;

Auth::require();

$filters = [
    'product_id' => $_GET['product_id'] ?? '',
    'from'       => $_GET['from']       ?? '',
    'to'         => $_GET['to']         ?? '',
    'search'     => $_GET['search']     ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$total = CustomerService::count($filters);
$rows  = CustomerService::list($filters, $perPage, ($page - 1) * $perPage);
$pages = max(1, (int) ceil($total / $perPage));

$products = ProductService::list([], 500);
$exportQs = http_build_query(array_filter($filters));

layout_head('Customers (VIP)', 'customers');
?>

<div class="section-head">
    <h2><?= number_format($total) ?> registered customers</h2>
</div>

<div class="card mb-2">
    <h3>📤 VIP Audience Export</h3>
    <p class="muted" style="margin:0 0 12px">Export customer lists ready for ad platforms. Filters below apply to the export.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= e(api_url('admin/customers-export.php?format=meta' . ($exportQs ? '&' . $exportQs : ''))) ?>" class="btn btn-secondary">
            📘 Meta Custom Audience
        </a>
        <a href="<?= e(api_url('admin/customers-export.php?format=google' . ($exportQs ? '&' . $exportQs : ''))) ?>" class="btn btn-secondary">
            🔵 Google Customer Match
        </a>
        <a href="<?= e(api_url('admin/customers-export.php?format=full' . ($exportQs ? '&' . $exportQs : ''))) ?>" class="btn btn-secondary">
            📊 Full Data Export
        </a>
    </div>
    <p class="muted" style="margin-top:10px;font-size:12px">
        <strong>Meta:</strong> columns <code>fn, ln, email</code> (lowercased) — upload to Meta Ads &gt; Audiences &gt; Custom Audience.<br>
        <strong>Google:</strong> columns <code>Email, First Name, Last Name</code> — upload to Google Ads &gt; Audience Manager &gt; Customer Match.<br>
        <strong>Full:</strong> All columns including phone, city, country, registration date.
    </p>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>Search</label>
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, email, phone…">
    </div>
    <div class="field">
        <label>First Product</label>
        <select name="product_id">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= (string) $filters['product_id'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($filters['to']) ?>">
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
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>City</th>
                <th>Country</th>
                <th>First Product</th>
                <th>Marketing</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center muted" style="padding:30px">No registrations match your filters.</td></tr>
        <?php else: foreach ($rows as $c): ?>
            <tr>
                <td><strong><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></strong></td>
                <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                <td class="col-mono"><?= e($c['phone'] ?? '—') ?></td>
                <td><?= e($c['city'] ?? '—') ?></td>
                <td class="col-mono"><?= e($c['country'] ?? '—') ?></td>
                <td><?= e($c['first_product_name'] ?? '—') ?></td>
                <td><span class="pill <?= ((int) $c['consent_marketing']) ? 'pill-active' : 'pill-inactive' ?>"><?= ((int) $c['consent_marketing']) ? 'Opted in' : 'Not opted' ?></span></td>
                <td class="col-mono" style="font-size:12px"><?= e(substr((string) $c['registered_at'], 0, 16)) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

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
