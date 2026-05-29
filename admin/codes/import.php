<?php
/**
 * Admin > Codes > Import (paste OR CSV)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\CodeService;
use Elhoe\ProductService;

Auth::require();

$products = ProductService::list(['status' => 'active'], 500);
$errors = [];
$pasteResult = null;
$csvResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired. Please retry.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'paste') {
            try {
                $pasteResult = CodeService::pasteImport([
                    'product_id'   => (int) ($_POST['paste_product_id'] ?? 0),
                    'batch_number' => $_POST['paste_batch_number'] ?? '',
                    'expiry_date'  => $_POST['paste_expiry_date']  ?? '',
                    'mode'         => $_POST['paste_mode']         ?? 'unique',
                    'codes'        => $_POST['paste_codes']        ?? '',
                ]);
                flash('success', count($pasteResult['created']) . ' codes imported, ' .
                                  count($pasteResult['skipped']) . ' duplicates skipped.');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'csv') {
            if (empty($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Please choose a CSV file.';
            } else {
                try {
                    $csvResult = CodeService::importCsv($_FILES['csv']['tmp_name'], [
                        'product_id'  => (int) ($_POST['csv_product_id'] ?? 0),
                        'mode'        => $_POST['csv_mode']        ?? 'universal',
                        'expiry_date' => $_POST['csv_expiry_date'] ?? '',
                    ]);
                    flash('success', "CSV imported: {$csvResult['created']} new, {$csvResult['updated']} updated, {$csvResult['skipped']} skipped.");
                } catch (\Throwable $e) {
                    $errors[] = 'Import failed: ' . $e->getMessage();
                }
            }
        }
    }
}

layout_head('Import Codes', 'codes');
?>

<div style="max-width:880px">
    <p class="muted"><a href="<?= e(admin_url('codes/list.php')) ?>">← Back to codes</a></p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="grid-2">

        <!-- Paste import -->
        <form method="POST" class="card">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="paste">

            <h2>📋 Paste Codes</h2>
            <p class="muted">All codes share the same product, batch, and settings.</p>

            <?php if ($pasteResult): ?>
                <div class="flash flash-info">
                    <?= count($pasteResult['created']) ?> created · <?= count($pasteResult['skipped']) ?> duplicates skipped
                    <?php if (!empty($pasteResult['errors'])): ?>
                        <details style="margin-top:6px"><summary>Errors</summary>
                            <ul><?php foreach ($pasteResult['errors'] as $err): ?><li style="font-size:12px"><?= e($err) ?></li><?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="field">
                <label>Product *</label>
                <select name="paste_product_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Batch</label>
                    <input name="paste_batch_number" maxlength="64">
                </div>
                <div class="field">
                    <label>Expiry</label>
                    <input name="paste_expiry_date" type="date">
                </div>
                <div class="field">
                    <label>Mode</label>
                    <select name="paste_mode">
                        <option value="unique">Unique</option>
                        <option value="universal">Universal</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Codes (one per line OR comma-separated)</label>
                <textarea name="paste_codes" rows="6" style="font-family:'JetBrains Mono',monospace" placeholder="123456789012&#10;987654321098&#10;456789012345"></textarea>
            </div>

            <button class="btn btn-primary">📥 Import Codes</button>
        </form>

        <!-- CSV import -->
        <form method="POST" enctype="multipart/form-data" class="card">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="csv">

            <h2>📂 CSV Import</h2>
            <p class="muted">Required column: <code>serial_code</code>. Optional: <code>wp_product_id</code>, <code>product_id</code>, <code>product_sku</code>, <code>batch_number</code>, <code>expiry_date</code>, <code>mode</code>, <code>status</code>, <code>scan_count</code>.</p>

            <?php if ($csvResult): ?>
                <div class="flash flash-info">
                    Created <?= (int) $csvResult['created'] ?> · Updated <?= (int) $csvResult['updated'] ?> · Skipped <?= (int) $csvResult['skipped'] ?>
                    <?php if (!empty($csvResult['errors'])): ?>
                        <details style="margin-top:6px"><summary>Row errors</summary>
                            <ul><?php foreach (array_slice($csvResult['errors'], 0, 30) as $err): ?><li style="font-size:12px"><?= e($err) ?></li><?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="font-size:12px;margin:0 0 12px"><a href="<?= e(api_url('admin/codes-template.php')) ?>">⬇ Download template</a></p>

            <div class="field">
                <label>Default Product (used if row doesn't specify one)</label>
                <select name="csv_product_id">
                    <option value="">— Use row's value —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Default Mode</label>
                    <select name="csv_mode">
                        <option value="universal">Universal</option>
                        <option value="unique">Unique</option>
                    </select>
                </div>
                <div class="field">
                    <label>Default Expiry</label>
                    <input name="csv_expiry_date" type="date">
                </div>
            </div>

            <div class="field">
                <label>CSV File</label>
                <input type="file" name="csv" accept=".csv,text/csv" required>
            </div>

            <button class="btn btn-primary">⬆ Upload &amp; Import CSV</button>
        </form>
    </div>
</div>

<?php layout_foot(); ?>
