<?php
/**
 * Admin > Integrations > Google Tag Manager
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Settings;
use Elhoe\AuditLog;

Auth::require();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired.';
    } else {
        $id = trim((string) ($_POST['gtm_container_id'] ?? ''));
        if ($id !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $id)) {
            $errors[] = 'Invalid GTM container ID format. Should look like GTM-XXXXXXX.';
        } else {
            Settings::set('gtm_container_id', $id);
            AuditLog::record('settings_gtm_save');
            flash('success', 'GTM settings saved.');
            redirect(admin_url('integrations/gtm.php'));
        }
    }
}

$gtmId = Settings::get('gtm_container_id');

layout_head('Google Tag Manager', 'integrations');
?>

<div class="tabs">
    <a href="<?= e(admin_url('integrations/meta.php')) ?>">Meta Pixel + CAPI</a>
    <a href="<?= e(admin_url('integrations/gtm.php')) ?>" class="is-active">Google Tag Manager</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <h2>GTM Container</h2>
        <p class="muted">Push <code>dataLayer</code> events on every verification and registration. Use these in GTM to fire GA4, Google Ads, or any other tag.</p>

        <div class="field">
            <label>GTM Container ID</label>
            <input name="gtm_container_id" value="<?= e($gtmId) ?>" placeholder="GTM-XXXXXXX" maxlength="20">
            <p class="field-help">Find in tagmanager.google.com → Workspace → top-right.</p>
        </div>

        <button class="btn btn-primary">Save</button>
    </form>

    <div class="card">
        <h3>dataLayer Events Pushed</h3>
        <table class="data" style="font-size:12px">
            <thead><tr><th>Event Name</th><th>When</th><th>Variables</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>spv_verify_success</code></td>
                    <td>Genuine product verified</td>
                    <td><code>product_name</code>, <code>serial_code</code>, <code>scenario</code></td>
                </tr>
                <tr>
                    <td><code>spv_verify_fail</code></td>
                    <td>Invalid / quarantined code</td>
                    <td><code>scenario</code></td>
                </tr>
                <tr>
                    <td><code>spv_registration</code></td>
                    <td>Customer registers product</td>
                    <td><code>product_name</code>, <code>serial_code</code></td>
                </tr>
            </tbody>
        </table>

        <hr class="divider">

        <h3 style="margin-top:0">GTM Trigger Setup</h3>
        <pre style="background:var(--admin-cream);padding:12px;border-radius:8px;font-size:12px;overflow-x:auto;margin:0">// In GTM, create a Custom Event trigger with:
//
//   Event Name = spv_verify_success
//
// Variables (Data Layer Variables):
//   - DLV - product_name
//   - DLV - serial_code
//   - DLV - scenario
//
// GA4 Event Tag config:
//   Event Name: product_verification
//   Parameters:
//     product_name -> {{DLV - product_name}}
//     serial_code  -> {{DLV - serial_code}}</pre>
    </div>
</div>

<?php layout_foot(); ?>
