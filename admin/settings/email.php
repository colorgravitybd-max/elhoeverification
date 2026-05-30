<?php
/**
 * Admin > Settings > Email Notifications + SMTP
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Settings;
use Elhoe\AuditLog;
use Elhoe\Mailer;

Auth::require();

$errors = [];
$test = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'save') {
            Settings::setMany([
                'email_notify_enabled'         => !empty($_POST['email_notify_enabled'])         ? '1' : '0',
                'email_notify_recipient'       => trim((string) ($_POST['email_notify_recipient']       ?? '')),
                'email_notify_from_name'       => trim((string) ($_POST['email_notify_from_name']       ?? 'ELHOE Verification')),
                'email_notify_from_address'    => trim((string) ($_POST['email_notify_from_address']    ?? '')),
                'email_notify_on_valid'        => !empty($_POST['email_notify_on_valid'])        ? '1' : '0',
                'email_notify_on_invalid'      => !empty($_POST['email_notify_on_invalid'])      ? '1' : '0',
                'email_notify_on_quarantine'   => !empty($_POST['email_notify_on_quarantine'])   ? '1' : '0',
                'email_notify_on_registration' => !empty($_POST['email_notify_on_registration']) ? '1' : '0',
                'email_notify_max_per_hour'    => max(1, (int) ($_POST['email_notify_max_per_hour'] ?? 60)),

                'smtp_enabled'  => !empty($_POST['smtp_enabled']) ? '1' : '0',
                'smtp_host'     => trim((string) ($_POST['smtp_host']     ?? '')),
                'smtp_port'     => (int) ($_POST['smtp_port']     ?? 465),
                'smtp_username' => trim((string) ($_POST['smtp_username'] ?? '')),
                'smtp_password' => (string)       ($_POST['smtp_password'] ?? ''),
                'smtp_secure'   => in_array($_POST['smtp_secure'] ?? '', ['ssl','tls',''], true) ? $_POST['smtp_secure'] : 'ssl',
            ]);
            AuditLog::record('settings_email_save');
            flash('success', 'Email settings saved.');
            redirect(admin_url('settings/email.php'));
        }

        if ($action === 'send_test') {
            $to = trim((string) ($_POST['test_to'] ?? ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $test = ['ok' => false, 'msg' => 'Please enter a valid recipient email.'];
            } else {
                $r = Mailer::send($to, '[TEST] ELHOE Verification email', '
                    <h2 style="color:#3E5641;font-family:Georgia,serif">It works!</h2>
                    <p>This is a test message from your ELHOE Verification admin panel. Receiving this confirms that:</p>
                    <ul>
                      <li>Your sender / from address is accepted</li>
                      <li>Your transport ('
                        . (Settings::get('smtp_enabled', '0') === '1' ? 'SMTP' : 'PHP mail()') .
                        ') is reachable</li>
                      <li>Your hosting allows outgoing mail from this account</li>
                    </ul>
                    <p>You can now turn on scan notifications and the system will email you whenever a code is verified.</p>
                ');
                $test = $r['ok']
                    ? ['ok' => true,  'msg' => 'Test sent. Check ' . $to . ' inbox (and spam folder).']
                    : ['ok' => false, 'msg' => 'Send failed: ' . ($r['error'] ?? 'unknown')];
                AuditLog::record('email_test', null, null, ['to' => $to, 'ok' => $r['ok']]);
            }
        }
    }
}

$s = [
    'enabled'    => Settings::get('email_notify_enabled', '0') === '1',
    'recipient'  => Settings::get('email_notify_recipient', ''),
    'from_name'  => Settings::get('email_notify_from_name', 'ELHOE Verification'),
    'from_addr'  => Settings::get('email_notify_from_address', 'no-reply@elhoe.com'),
    'on_valid'   => Settings::get('email_notify_on_valid', '0')        === '1',
    'on_invalid' => Settings::get('email_notify_on_invalid', '0')      === '1',
    'on_quarant' => Settings::get('email_notify_on_quarantine', '0')   === '1',
    'on_regist'  => Settings::get('email_notify_on_registration', '0') === '1',
    'max_hour'   => (int) Settings::get('email_notify_max_per_hour', 60),

    'smtp_enabled'  => Settings::get('smtp_enabled', '0') === '1',
    'smtp_host'     => Settings::get('smtp_host', 'smtp.hostinger.com'),
    'smtp_port'     => (int) Settings::get('smtp_port', 465),
    'smtp_username' => Settings::get('smtp_username', ''),
    'smtp_password' => Settings::get('smtp_password', ''),
    'smtp_secure'   => Settings::get('smtp_secure', 'ssl'),
];

layout_head('Email Settings', 'settings');
?>

<div class="tabs">
    <a href="<?= e(admin_url('settings/general.php')) ?>">General</a>
    <a href="<?= e(admin_url('settings/email.php')) ?>" class="is-active">Email</a>
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

        <h2>Notifications</h2>
        <p class="muted">Send an email to admin every time a customer scans a code. Useful for real-time monitoring of counterfeit attempts.</p>

        <label class="checkbox-field" style="font-weight:600;font-size:14px">
            <input type="checkbox" name="email_notify_enabled" value="1" <?= $s['enabled'] ? 'checked' : '' ?>>
            Enable email notifications
        </label>

        <div class="field">
            <label>Recipient(s)</label>
            <input name="email_notify_recipient" value="<?= e($s['recipient']) ?>" placeholder="you@elhoe.com, alerts@elhoe.com">
            <p class="field-help">Comma-separated. Leave empty to fall back to "Admin Alert Email" in General settings.</p>
        </div>

        <div class="field-row">
            <div class="field">
                <label>From Name</label>
                <input name="email_notify_from_name" value="<?= e($s['from_name']) ?>" maxlength="100">
            </div>
            <div class="field">
                <label>From Address</label>
                <input name="email_notify_from_address" type="email" value="<?= e($s['from_addr']) ?>" placeholder="no-reply@elhoe.com">
                <p class="field-help">Must match your domain for best deliverability.</p>
            </div>
        </div>

        <h3 style="margin-top:18px">Notify on...</h3>
        <label class="checkbox-field"><input type="checkbox" name="email_notify_on_valid"        value="1" <?= $s['on_valid']   ? 'checked' : '' ?>> Genuine product scanned</label>
        <label class="checkbox-field"><input type="checkbox" name="email_notify_on_invalid"      value="1" <?= $s['on_invalid'] ? 'checked' : '' ?>> Counterfeit / failed scan</label>
        <label class="checkbox-field"><input type="checkbox" name="email_notify_on_quarantine"   value="1" <?= $s['on_quarant'] ? 'checked' : '' ?>> Code auto-quarantined (high risk)</label>
        <label class="checkbox-field"><input type="checkbox" name="email_notify_on_registration" value="1" <?= $s['on_regist']  ? 'checked' : '' ?>> New customer registration</label>

        <div class="field" style="margin-top:14px">
            <label>Hourly cap (anti-spam)</label>
            <input type="number" name="email_notify_max_per_hour" value="<?= (int) $s['max_hour'] ?>" min="1" max="500" style="max-width:120px">
            <p class="field-help">Maximum emails per hour across all events. Hostinger's mail() limit is ~100/hr.</p>
        </div>

        <hr class="divider">

        <h2>SMTP (optional)</h2>
        <p class="muted">For best deliverability use SMTP with a Hostinger-hosted email account. Otherwise messages go via PHP mail().</p>

        <label class="checkbox-field" style="font-weight:600;font-size:14px">
            <input type="checkbox" name="smtp_enabled" value="1" <?= $s['smtp_enabled'] ? 'checked' : '' ?>>
            Use SMTP instead of PHP mail()
        </label>

        <div class="field-row">
            <div class="field">
                <label>SMTP Host</label>
                <input name="smtp_host" value="<?= e($s['smtp_host']) ?>" placeholder="smtp.hostinger.com">
            </div>
            <div class="field">
                <label>Port</label>
                <input name="smtp_port" type="number" value="<?= (int) $s['smtp_port'] ?>" min="1" max="65535">
            </div>
            <div class="field">
                <label>Encryption</label>
                <select name="smtp_secure">
                    <option value="ssl" <?= $s['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
                    <option value="tls" <?= $s['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (587)</option>
                    <option value=""    <?= $s['smtp_secure'] === ''    ? 'selected' : '' ?>>None</option>
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label>SMTP Username</label>
                <input name="smtp_username" value="<?= e($s['smtp_username']) ?>" placeholder="bd@elhoe.com" autocomplete="off">
            </div>
            <div class="field">
                <label>SMTP Password</label>
                <input name="smtp_password" type="password" value="<?= e($s['smtp_password']) ?>" autocomplete="new-password">
            </div>
        </div>

        <button class="btn btn-primary">Save Email Settings</button>
    </form>

    <div>
        <form method="POST" class="card mb-2">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="send_test">
            <h3>Send Test Email</h3>
            <p class="muted">Verify your config before going live.</p>
            <div class="field">
                <label>Recipient</label>
                <input name="test_to" type="email" placeholder="you@elhoe.com" required>
            </div>
            <button class="btn btn-secondary">Send Test</button>
            <?php if ($test): ?>
                <div class="flash flash-<?= $test['ok'] ? 'success' : 'error' ?>" style="margin-top:12px"><?= e($test['msg']) ?></div>
            <?php endif; ?>
        </form>

        <div class="card">
            <h3>Hostinger SMTP Quick Reference</h3>
            <table class="data" style="font-size:12px">
                <tbody>
                    <tr><td><strong>Host</strong></td><td><code>smtp.hostinger.com</code></td></tr>
                    <tr><td><strong>Port (SSL)</strong></td><td><code>465</code></td></tr>
                    <tr><td><strong>Port (TLS)</strong></td><td><code>587</code></td></tr>
                    <tr><td><strong>Username</strong></td><td>your email account (e.g. <code>bd@elhoe.com</code>)</td></tr>
                    <tr><td><strong>Password</strong></td><td>your email account password</td></tr>
                </tbody>
            </table>
            <p class="muted" style="font-size:11px;margin-top:10px">Find/create email accounts in hPanel &rarr; Emails &rarr; Email Accounts.</p>
        </div>
    </div>
</div>

<?php layout_foot(); ?>
