<?php
/**
 * Admin > Codes > Edit single code
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\CodeService;
use Elhoe\ProductService;
use Elhoe\Database;

Auth::require();

$id = (int) ($_GET['id'] ?? 0);
$code = $id > 0 ? CodeService::find($id) : null;
if (!$code) {
    flash('error', 'Code not found.');
    redirect(admin_url('codes/list.php'));
}

$products = ProductService::list([], 500);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired. Please retry.';
    } else {
        try {
            CodeService::update($id, [
                'product_id'              => (int) ($_POST['product_id'] ?? 0),
                'batch_number'            => $_POST['batch_number'] ?? '',
                'expiry_date'             => $_POST['expiry_date']  ?? '',
                'mode'                    => $_POST['mode']         ?? 'unique',
                'status'                  => $_POST['status']       ?? 'active',
                'recommended_product_ids' => $_POST['recommended_product_ids'] ?? '',
                'notes'                   => $_POST['notes']        ?? '',
            ]);
            flash('success', 'Code updated.');
            redirect(admin_url('codes/edit.php?id=' . $id));
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Recent scans for this code
$recentScans = Database::all(
    "SELECT * FROM scan_logs WHERE code_id = :id ORDER BY scanned_at DESC LIMIT 20",
    [':id' => $id]
);

// Owner
$owner = null;
if (!empty($code['owner_customer_id'])) {
    $owner = Database::one("SELECT * FROM customers WHERE id = :id", [':id' => $code['owner_customer_id']]);
}

layout_head('Edit Code', 'codes');
?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
<div>
    <p class="muted"><a href="<?= e(admin_url('codes/list.php')) ?>">← Back to codes</a></p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <h2 style="font-family:'JetBrains Mono',monospace;font-size:22px"><?= e($code['code']) ?></h2>
        <p class="muted">Code ID: <?= (int) $code['id'] ?> · Created <?= e($code['created_at']) ?></p>

        <div class="field">
            <label>Product *</label>
            <select name="product_id" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>" <?= (int) $p['id'] === (int) $code['product_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-row">
            <div class="field"><label>Batch</label><input name="batch_number" maxlength="64" value="<?= e($code['batch_number']) ?>"></div>
            <div class="field"><label>Expiry</label><input name="expiry_date" type="date" value="<?= e($code['expiry_date']) ?>"></div>
            <div class="field">
                <label>Mode</label>
                <select name="mode">
                    <option value="unique"    <?= $code['mode'] === 'unique'    ? 'selected' : '' ?>>Unique</option>
                    <option value="universal" <?= $code['mode'] === 'universal' ? 'selected' : '' ?>>Universal</option>
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="active"      <?= $code['status'] === 'active'      ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"    <?= $code['status'] === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
                    <option value="quarantined" <?= $code['status'] === 'quarantined' ? 'selected' : '' ?>>Quarantined</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Recommended Products (override routine)</label>
            <input name="recommended_product_ids" value="<?= e($code['recommended_product_ids']) ?>" placeholder="12,15,18">
        </div>

        <div class="field">
            <label>Internal Notes</label>
            <textarea name="notes" rows="3"><?= e($code['notes']) ?></textarea>
        </div>

        <button class="btn btn-primary">Save Changes</button>
        <a href="<?= e(admin_url('codes/list.php')) ?>" class="btn btn-ghost">Cancel</a>
    </form>

    <h3 style="margin-top:20px">Recent Scans</h3>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>Time</th><th>IP</th><th>City</th><th>Country</th><th>Result</th></tr>
            </thead>
            <tbody>
                <?php if (!$recentScans): ?>
                    <tr><td colspan="5" class="text-center muted" style="padding:20px">No scans yet.</td></tr>
                <?php else: foreach ($recentScans as $s): ?>
                    <tr>
                        <td class="col-mono"><?= e($s['scanned_at']) ?></td>
                        <td class="col-mono"><?= e($s['ip_address']) ?></td>
                        <td><?= e($s['ip_city']) ?></td>
                        <td><?= e($s['ip_country_name']) ?></td>
                        <td><span class="pill pill-<?= str_starts_with($s['result'], 'valid') || $s['result'] === 'already_registered' ? 'success' : 'fail' ?>"><?= e($s['result']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<aside>
    <div class="card mb-2">
        <h3>Stats</h3>
        <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:13px">
            <dt class="muted">Scans</dt><dd><?= number_format((int) $code['scan_count']) ?></dd>
            <dt class="muted">First scan</dt><dd><?= e($code['first_scanned_at'] ?? '—') ?></dd>
            <dt class="muted">First IP</dt><dd class="col-mono"><?= e($code['first_scan_ip'] ?? '—') ?></dd>
            <dt class="muted">Last scan</dt><dd><?= e($code['last_scanned_at'] ?? '—') ?></dd>
            <dt class="muted">Risk</dt><dd><strong style="<?= ((int) $code['risk_score']) >= 70 ? 'color:var(--admin-danger)' : (((int) $code['risk_score']) >= 50 ? 'color:var(--admin-warning)' : '') ?>"><?= (int) $code['risk_score'] ?>/100</strong></dd>
        </dl>
    </div>

    <?php if ($owner): ?>
    <div class="card mb-2">
        <h3>Registered Owner</h3>
        <p style="margin:0;font-size:13px">
            <strong><?= e(trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''))) ?></strong><br>
            <a href="mailto:<?= e($owner['email']) ?>"><?= e($owner['email']) ?></a><br>
            <?php if ($owner['phone']): ?><span class="muted"><?= e($owner['phone']) ?></span><br><?php endif; ?>
            <?php if ($owner['city']): ?><span class="muted"><?= e($owner['city']) ?></span><?php endif; ?>
        </p>
        <p class="muted" style="font-size:12px;margin-top:8px">Registered <?= e($code['registered_at']) ?></p>
    </div>
    <?php endif; ?>
</aside>
</div>

<?php layout_foot(); ?>
