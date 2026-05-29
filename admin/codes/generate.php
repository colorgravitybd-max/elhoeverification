<?php
/**
 * Admin > Codes > Generate (auto-generate numeric codes)
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

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired. Please retry.';
    } else {
        try {
            $result = CodeService::generate([
                'product_id'   => (int) ($_POST['product_id'] ?? 0),
                'batch_number' => $_POST['batch_number'] ?? '',
                'expiry_date'  => $_POST['expiry_date']  ?? '',
                'mode'         => $_POST['mode']         ?? 'unique',
                'length'       => (int) ($_POST['length']   ?? 12),
                'quantity'     => (int) ($_POST['quantity'] ?? 1),
                'recommended_product_ids' => trim((string) ($_POST['recommended_product_ids'] ?? '')),
            ]);
            flash('success', count($result['created']) . ' code(s) generated.');
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

layout_head('Generate Codes', 'codes');
?>

<div style="max-width:760px">
    <p class="muted"><a href="<?= e(admin_url('codes/list.php')) ?>">← Back to codes</a></p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($result && !empty($result['created'])): ?>
        <div class="card mb-2">
            <h3>Generated <?= count($result['created']) ?> codes</h3>
            <textarea readonly rows="6" style="width:100%;font-family:'JetBrains Mono',monospace;font-size:12px;padding:10px;border:1px solid var(--admin-border);border-radius:8px"><?php
                echo e(implode("\n", array_column($result['created'], 'code')));
            ?></textarea>
            <p class="muted" style="margin-top:6px;font-size:12px">Copy these codes for your label printer. They are also saved in the master code list.</p>
        </div>
    <?php endif; ?>

    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <h2>Auto-Generate Numeric Codes</h2>

        <div class="field">
            <label for="product_id">Product *</label>
            <select id="product_id" name="product_id" required>
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> <?php if ($p['sku']): ?>(<?= e($p['sku']) ?>)<?php endif; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="batch_number">Batch Number</label>
                <input id="batch_number" name="batch_number" maxlength="64" placeholder="BATCH-2026-001" value="<?= e($_POST['batch_number'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="expiry_date">Expiry Date</label>
                <input id="expiry_date" name="expiry_date" type="date" value="<?= e($_POST['expiry_date'] ?? '') ?>">
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="mode">Verification Mode</label>
                <select id="mode" name="mode">
                    <option value="unique"    <?= ($_POST['mode'] ?? 'unique') === 'unique' ? 'selected' : '' ?>>Unique – one code per unit</option>
                    <option value="universal" <?= ($_POST['mode'] ?? '') === 'universal' ? 'selected' : '' ?>>Universal – shared batch code</option>
                </select>
            </div>
            <div class="field">
                <label for="length">Code Length (digits)</label>
                <input id="length" name="length" type="number" min="8" max="16" value="<?= e($_POST['length'] ?? 12) ?>">
            </div>
            <div class="field">
                <label for="quantity">Quantity</label>
                <input id="quantity" name="quantity" type="number" min="1" max="10000" value="<?= e($_POST['quantity'] ?? 100) ?>">
                <p class="field-help">Universal mode forces quantity = 1.</p>
            </div>
        </div>

        <div class="field">
            <label for="recommended_product_ids">Recommended Products (Complete Your Routine)</label>
            <input id="recommended_product_ids" name="recommended_product_ids" placeholder="e.g. 12,15,18 (product IDs)" value="<?= e($_POST['recommended_product_ids'] ?? '') ?>">
            <p class="field-help">Comma-separated product IDs. Leave empty to use the product's routine_group siblings.</p>
        </div>

        <button type="submit" class="btn btn-primary">✨ Generate Codes</button>
    </form>
</div>

<?php layout_foot(); ?>
