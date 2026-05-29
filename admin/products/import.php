<?php
/**
 * Admin > Products > CSV Import
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\ProductService;

Auth::require();

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired. Please retry.';
    } elseif (empty($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a CSV file.';
    } else {
        $tmp = $_FILES['csv']['tmp_name'];
        try {
            $result = ProductService::importCsv($tmp);
            flash('success', "Imported: {$result['created']} new, {$result['updated']} updated, {$result['skipped']} skipped.");
        } catch (\Throwable $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

layout_head('Import Products', 'products');
?>

<div style="max-width:760px">
    <p class="muted"><a href="<?= e(admin_url('products/list.php')) ?>">← Back to products</a></p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($result): ?>
        <div class="card mb-2">
            <h3>Import Summary</h3>
            <ul style="margin:0;padding-left:18px">
                <li><strong><?= (int) $result['created'] ?></strong> new products created</li>
                <li><strong><?= (int) $result['updated'] ?></strong> existing products updated</li>
                <li><strong><?= (int) $result['skipped'] ?></strong> skipped</li>
            </ul>
            <?php if (!empty($result['errors'])): ?>
                <details style="margin-top:10px">
                    <summary style="cursor:pointer">View row errors (<?= count($result['errors']) ?>)</summary>
                    <ul style="font-size:12px;margin-top:8px;color:var(--admin-danger)">
                        <?php foreach (array_slice($result['errors'], 0, 50) as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card">
        <?= CSRF::field() ?>
        <h2>Import Products from CSV</h2>

        <p class="muted">Upload a CSV file with the following columns. The <code>name</code> column is required; everything else is optional.</p>

        <div style="background:var(--admin-cream);padding:10px 14px;border-radius:8px;font-family:'JetBrains Mono',monospace;font-size:12px;margin-bottom:14px;overflow-x:auto;white-space:pre">
wp_product_id,sku,name,slug,category,routine_group,image_url,product_url,description,status
2387,ELH-LS-001,ELHOE Lightening Serum,,Serums,Brightening,https://elhoe.com/.../lightening.jpg,https://elhoe.com/product/lightening-serum/,A natural serum...,active
2779,ELH-PSM-001,ELHOE Pregnancy Stretch Mark Gel,,Body Care,Pregnancy,https://elhoe.com/.../psm.jpg,https://elhoe.com/product/psm/,...,active
        </div>

        <p style="font-size:13px;color:var(--admin-muted);margin:0 0 14px">
            • Duplicates are matched by <strong>SKU</strong>, then by <strong>WP Product ID</strong>, then by <strong>slug</strong>.<br>
            • Existing products are updated. Missing columns keep current values for updates.<br>
            • Need a starter file? <a href="<?= e(api_url('admin/products-template.php')) ?>">Download template CSV</a>
        </p>

        <div class="field">
            <label for="csv">CSV file</label>
            <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
        </div>

        <button type="submit" class="btn btn-primary">↑ Upload &amp; Import</button>
    </form>
</div>

<?php layout_foot(); ?>
