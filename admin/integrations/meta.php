<?php
/**
 * Admin > Integrations > Meta Pixel + Conversions API
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Settings;
use Elhoe\PixelDispatcher;
use Elhoe\AuditLog;

Auth::require();

$test = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            Settings::setMany([
                'meta_pixel_id'       => trim((string) ($_POST['meta_pixel_id']       ?? '')),
                'meta_capi_token'     => trim((string) ($_POST['meta_capi_token']     ?? '')),
                'meta_capi_test_code' => trim((string) ($_POST['meta_capi_test_code'] ?? '')),
            ]);
            AuditLog::record('settings_meta_save');
            flash('success', 'Meta integration saved.');
            redirect(admin_url('integrations/meta.php'));
        } elseif ($action === 'fire_test') {
            $ok = PixelDispatcher::fireServerEvent('Lead', [
                'content_name'     => 'Test Event',
                'content_category' => 'TestEvent',
            ]);
            $test = $ok
                ? ['ok' => true,  'msg' => 'Test event fired successfully. Check Meta Events Manager → Test Events tab (filter by your test code).']
                : ['ok' => false, 'msg' => 'Test event failed. Verify Pixel ID + access token are correct, then try again.'];
        }
    }
}

$pixelId   = Settings::get('meta_pixel_id');
$capiToken = Settings::get('meta_capi_token');
$testCode  = Settings::get('meta_capi_test_code');

layout_head('Meta Integration', 'integrations');
?>

<div class="tabs">
    <a href="<?= e(admin_url('integrations/meta.php')) ?>" class="is-active">Meta Pixel + CAPI</a>
    <a href="<?= e(admin_url('integrations/gtm.php')) ?>">Google Tag Manager</a>
</div>

<div class="grid-2">
    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save">

        <h2>Meta Pixel</h2>
        <p class="muted">Fires browser-side events on every verification (PageView, ViewContent, Lead, Search).</p>

        <div class="field">
            <label>Pixel ID</label>
            <input name="meta_pixel_id" value="<?= e($pixelId) ?>" placeholder="1234567890123456" maxlength="32">
            <p class="field-help">Find this in Meta Business Manager → Events Manager → Data Sources.</p>
        </div>

        <hr class="divider">

        <h2>Conversions API (server-side)</h2>
        <p class="muted">Mirrors events server-side for higher match rate and bypasses ad-blockers / iOS limits.</p>

        <div class="field">
            <label>CAPI Access Token</label>
            <input name="meta_capi_token" type="password" value="<?= e($capiToken) ?>" placeholder="EAAxxxxx..." autocomplete="off">
            <p class="field-help">Generate in Events Manager → Settings → Conversions API → Generate Access Token.</p>
        </div>

        <div class="field">
            <label>Test Event Code (optional)</label>
            <input name="meta_capi_test_code" value="<?= e($testCode) ?>" placeholder="TEST12345" maxlength="32">
            <p class="field-help">Set during testing only. Leave empty in production.</p>
        </div>

        <button class="btn btn-primary">Save</button>
    </form>

    <div>
        <form method="POST" class="card mb-2">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="fire_test">
            <h3>Fire Test Event</h3>
            <p class="muted">Sends a "Lead" event to verify your CAPI integration. Confirm in Meta Events Manager → Test Events.</p>
            <button class="btn btn-secondary" <?= ($pixelId && $capiToken) ? '' : 'disabled' ?>>▶ Fire Test Event</button>
            <?php if ($test): ?>
                <div class="flash flash-<?= $test['ok'] ? 'success' : 'error' ?>" style="margin-top:12px"><?= e($test['msg']) ?></div>
            <?php endif; ?>
        </form>

        <div class="card">
            <h3>Events Fired</h3>
            <table class="data" style="font-size:12px">
                <thead><tr><th>Event</th><th>Trigger</th></tr></thead>
                <tbody>
                    <tr><td><strong>PageView</strong></td><td>Customer opens /checker</td></tr>
                    <tr><td><strong>ViewContent</strong></td><td>Genuine product verified</td></tr>
                    <tr><td><strong>Lead</strong></td><td>Customer registers product</td></tr>
                    <tr><td><strong>Search</strong></td><td>Invalid code entered</td></tr>
                </tbody>
            </table>
            <p class="muted" style="font-size:12px;margin-top:10px">Pixel events fire client-side; CAPI mirrors server-side with hashed PII.</p>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
