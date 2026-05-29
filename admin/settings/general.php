<?php
/**
 * Admin > Settings > General (brand identity + support contacts)
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
        $action = $_POST['action'] ?? 'save';

        if ($action === 'logo_upload' && !empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $size = (int) $_FILES['logo']['size'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Logo must be PNG, JPG, SVG or WebP.';
            } elseif ($size > 2 * 1024 * 1024) {
                $errors[] = 'Logo must be under 2 MB.';
            } else {
                $uploadDir = STORAGE_PATH . '/uploads';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
                $filename = 'logo-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
                $dest = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    // Public-accessible copy in public/assets/images/
                    $publicLogo = PUBLIC_PATH . '/assets/images/' . $filename;
                    @copy($dest, $publicLogo);
                    Settings::set('brand_logo_url', asset_url('images/' . $filename));
                    AuditLog::record('settings_logo_upload', null, null, ['file' => $filename]);
                    flash('success', 'Logo uploaded.');
                    redirect(admin_url('settings/general.php'));
                } else {
                    $errors[] = 'Could not save the uploaded file. Check storage/uploads permissions.';
                }
            }
        } elseif ($action === 'save') {
            Settings::setMany([
                'brand_name'           => trim((string) ($_POST['brand_name']           ?? '')),
                'brand_tagline'        => trim((string) ($_POST['brand_tagline']        ?? '')),
                'brand_primary_color'  => trim((string) ($_POST['brand_primary_color']  ?? '#3E5641')),
                'brand_accent_color'   => trim((string) ($_POST['brand_accent_color']   ?? '#A4B494')),
                'brand_bg_color'       => trim((string) ($_POST['brand_bg_color']       ?? '#F5F1E8')),
                'support_email'        => trim((string) ($_POST['support_email']        ?? '')),
                'support_whatsapp'     => trim((string) ($_POST['support_whatsapp']     ?? '')),
                'admin_alert_email'    => trim((string) ($_POST['admin_alert_email']    ?? '')),
            ]);
            AuditLog::record('settings_general_save');
            flash('success', 'Settings saved.');
            redirect(admin_url('settings/general.php'));
        } elseif ($action === 'logo_remove') {
            Settings::set('brand_logo_url', '');
            AuditLog::record('settings_logo_remove');
            flash('success', 'Logo removed (will fall back to brand text).');
            redirect(admin_url('settings/general.php'));
        }
    }
}

$s = [
    'brand_name'          => Settings::get('brand_name', 'ELHOE'),
    'brand_tagline'       => Settings::get('brand_tagline', 'Redefine Your Skincare Journey'),
    'brand_logo_url'      => Settings::get('brand_logo_url', ''),
    'brand_primary_color' => Settings::get('brand_primary_color', '#3E5641'),
    'brand_accent_color'  => Settings::get('brand_accent_color', '#A4B494'),
    'brand_bg_color'      => Settings::get('brand_bg_color', '#F5F1E8'),
    'support_email'       => Settings::get('support_email', ''),
    'support_whatsapp'    => Settings::get('support_whatsapp', ''),
    'admin_alert_email'   => Settings::get('admin_alert_email', ''),
];

layout_head('General Settings', 'settings');
?>

<div class="tabs">
    <a href="<?= e(admin_url('settings/general.php')) ?>" class="is-active">General</a>
    <a href="<?= e(admin_url('settings/admins.php')) ?>">Admins</a>
    <a href="<?= e(admin_url('settings/backup.php')) ?>">Backup</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save">

        <h2>Brand Identity</h2>

        <div class="field-row">
            <div class="field">
                <label>Brand Name</label>
                <input name="brand_name" value="<?= e($s['brand_name']) ?>" maxlength="100">
            </div>
            <div class="field">
                <label>Tagline</label>
                <input name="brand_tagline" value="<?= e($s['brand_tagline']) ?>" maxlength="200">
            </div>
        </div>

        <h3 style="margin-top:18px">Theme Colors</h3>
        <div class="field-row">
            <div class="field">
                <label>Primary</label>
                <input type="color" name="brand_primary_color" value="<?= e($s['brand_primary_color']) ?>">
            </div>
            <div class="field">
                <label>Accent</label>
                <input type="color" name="brand_accent_color" value="<?= e($s['brand_accent_color']) ?>">
            </div>
            <div class="field">
                <label>Background</label>
                <input type="color" name="brand_bg_color" value="<?= e($s['brand_bg_color']) ?>">
            </div>
        </div>

        <hr class="divider">

        <h2>Support &amp; Notifications</h2>

        <div class="field">
            <label>Support Email</label>
            <input type="email" name="support_email" value="<?= e($s['support_email']) ?>" placeholder="support@elhoe.com">
            <p class="field-help">Shown on quarantine / contact prompts.</p>
        </div>

        <div class="field">
            <label>Support WhatsApp (optional)</label>
            <input name="support_whatsapp" value="<?= e($s['support_whatsapp']) ?>" placeholder="+8801XXXXXXXXX">
        </div>

        <div class="field">
            <label>Admin Alert Email</label>
            <input type="email" name="admin_alert_email" value="<?= e($s['admin_alert_email']) ?>" placeholder="alerts@elhoe.com">
            <p class="field-help">Receives notifications when codes are auto-quarantined or abuse is detected.</p>
        </div>

        <button class="btn btn-primary">Save Settings</button>
    </form>

    <div>
        <form method="POST" enctype="multipart/form-data" class="card mb-2">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="logo_upload">

            <h3>Brand Logo</h3>
            <?php if ($s['brand_logo_url']): ?>
                <div style="background:var(--admin-cream);padding:14px;border-radius:8px;margin-bottom:10px;text-align:center">
                    <img src="<?= e($s['brand_logo_url']) ?>" alt="Logo" style="max-height:60px">
                </div>
            <?php else: ?>
                <p class="muted" style="font-size:12px;margin:0 0 10px">No logo uploaded — falls back to brand name in <em>Cormorant Garamond</em> serif font.</p>
            <?php endif; ?>

            <div class="field">
                <label>Upload (PNG, SVG, JPG, WebP — max 2 MB)</label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
            </div>
            <button class="btn btn-secondary">Upload</button>
            <?php if ($s['brand_logo_url']): ?>
                <button type="submit" name="action" value="logo_remove" class="btn btn-ghost btn-sm" style="color:var(--admin-danger)" onclick="return confirm('Remove the current logo?')">Remove</button>
            <?php endif; ?>
        </form>

        <div class="card">
            <h3>Live Preview</h3>
            <div style="border:1px solid var(--admin-border);border-radius:10px;padding:24px;background:<?= e($s['brand_bg_color']) ?>;text-align:center">
                <?php if ($s['brand_logo_url']): ?>
                    <img src="<?= e($s['brand_logo_url']) ?>" alt="" style="max-height:42px;margin-bottom:10px">
                <?php else: ?>
                    <div style="font-family:'Cormorant Garamond',serif;font-size:34px;font-weight:600;color:<?= e($s['brand_primary_color']) ?>;margin-bottom:6px"><?= e($s['brand_name']) ?></div>
                <?php endif; ?>
                <div style="font-style:italic;color:var(--admin-muted);font-size:13px"><?= e($s['brand_tagline']) ?></div>
                <button style="margin-top:14px;background:<?= e($s['brand_primary_color']) ?>;color:#fff;border:none;padding:9px 18px;border-radius:999px;font-weight:600;font-size:13px">Verify Authenticity</button>
            </div>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
