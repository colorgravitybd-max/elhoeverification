<?php
/**
 * Admin > Settings > Admins (manage admin users) - super_admin only
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_layout.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Database;
use Elhoe\AuditLog;

$me = Auth::requireRole('super_admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expired.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $email    = trim((string) ($_POST['email']    ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role     = $_POST['role'] ?? 'manager';
            if (!in_array($role, ['super_admin', 'manager', 'marketer'], true)) $role = 'manager';
            if ($username === '' || strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email required.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } else {
                try {
                    $newId = Database::insert('admin_users', [
                        'username'      => $username,
                        'email'         => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        'role'          => $role,
                        'status'        => 'active',
                    ]);
                    AuditLog::record('admin_create', 'admin_user', $newId, ['username' => $username, 'role' => $role]);
                    flash('success', "Admin '{$username}' created.");
                    redirect(admin_url('settings/admins.php'));
                } catch (\Throwable $e) {
                    $errors[] = 'Could not create admin: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'reset_password') {
            $id = (int) ($_POST['id'] ?? 0);
            $pw = (string) ($_POST['new_password'] ?? '');
            if (strlen($pw) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } elseif ($id > 0) {
                Database::exec("UPDATE admin_users SET password_hash = :h, failed_attempts = 0, locked_until = NULL WHERE id = :id",
                    [':h' => password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), ':id' => $id]);
                AuditLog::record('admin_reset_password', 'admin_user', $id);
                flash('success', 'Password reset.');
                redirect(admin_url('settings/admins.php'));
            }
        } elseif ($action === 'set_status' || $action === 'set_role') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $me['id']) {
                $errors[] = "You can't change your own account.";
            } elseif ($action === 'set_status') {
                $st = $_POST['status'] ?? '';
                if (!in_array($st, ['active', 'disabled'], true)) {
                    $errors[] = 'Invalid status.';
                } else {
                    Database::exec("UPDATE admin_users SET status = :s WHERE id = :id", [':s' => $st, ':id' => $id]);
                    AuditLog::record('admin_status', 'admin_user', $id, ['status' => $st]);
                    flash('success', 'Status updated.');
                    redirect(admin_url('settings/admins.php'));
                }
            } elseif ($action === 'set_role') {
                $r = $_POST['role'] ?? '';
                if (!in_array($r, ['super_admin', 'manager', 'marketer'], true)) {
                    $errors[] = 'Invalid role.';
                } else {
                    Database::exec("UPDATE admin_users SET role = :r WHERE id = :id", [':r' => $r, ':id' => $id]);
                    AuditLog::record('admin_role', 'admin_user', $id, ['role' => $r]);
                    flash('success', 'Role updated.');
                    redirect(admin_url('settings/admins.php'));
                }
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $me['id']) {
                $errors[] = "You can't delete your own account.";
            } elseif ($id > 0) {
                Database::exec("DELETE FROM admin_users WHERE id = :id", [':id' => $id]);
                AuditLog::record('admin_delete', 'admin_user', $id);
                flash('success', 'Admin deleted.');
                redirect(admin_url('settings/admins.php'));
            }
        }
    }
}

$admins = Database::all("SELECT * FROM admin_users ORDER BY created_at ASC");

layout_head('Admin Users', 'settings');
?>

<div class="tabs">
    <a href="<?= e(admin_url('settings/general.php')) ?>">General</a>
    <a href="<?= e(admin_url('settings/email.php')) ?>">Email</a>
    <a href="<?= e(admin_url('settings/admins.php')) ?>" class="is-active">Admins</a>
    <a href="<?= e(admin_url('settings/backup.php')) ?>">Backup</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
    <div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th class="col-actions">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($admins as $u): ?>
                    <tr>
                        <td><strong><?= e($u['username']) ?></strong><?php if ((int) $u['id'] === (int) $me['id']): ?> <span class="muted">(you)</span><?php endif; ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <?php if ((int) $u['id'] === (int) $me['id']): ?>
                                <span class="pill pill-active"><?= e($u['role']) ?></span>
                            <?php else: ?>
                                <form method="POST" style="display:inline">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <select name="role" onchange="if(confirm('Change role to ' + this.value + '?')) this.form.submit()">
                                        <option value="manager"     <?= $u['role'] === 'manager'     ? 'selected' : '' ?>>manager</option>
                                        <option value="marketer"    <?= $u['role'] === 'marketer'    ? 'selected' : '' ?>>marketer</option>
                                        <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>super_admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill pill-<?= $u['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e($u['status']) ?></span></td>
                        <td class="col-mono" style="font-size:11px"><?= e($u['last_login_at'] ?? '—') ?></td>
                        <td class="col-actions">
                            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                                <form method="POST" style="display:inline">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>">
                                    <button class="btn btn-ghost btn-sm"><?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete ' + <?= json_encode($u['username']) ?> + '?')">
                                    <?= CSRF::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <button class="btn btn-ghost btn-sm" style="color:var(--admin-danger)">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details class="card mt-2">
            <summary style="cursor:pointer;font-weight:600">Reset password for an admin</summary>
            <form method="POST" style="margin-top:14px">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="reset_password">
                <div class="field-row">
                    <div class="field">
                        <label>Admin</label>
                        <select name="id" required>
                            <?php foreach ($admins as $u): ?>
                                <option value="<?= e($u['id']) ?>"><?= e($u['username']) ?> (<?= e($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="8" required>
                    </div>
                </div>
                <button class="btn btn-secondary">Reset Password</button>
            </form>
        </details>
    </div>

    <form method="POST" class="card">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="create">

        <h2>+ Add Admin</h2>

        <div class="field"><label>Username</label><input name="username" minlength="3" maxlength="50" required></div>
        <div class="field"><label>Email</label><input name="email" type="email" maxlength="255" required></div>
        <div class="field"><label>Password</label><input name="password" type="password" minlength="8" required></div>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <option value="manager">Manager (full access except admin users)</option>
                <option value="marketer">Marketer (analytics + exports only)</option>
                <option value="super_admin">Super Admin (everything)</option>
            </select>
        </div>

        <button class="btn btn-primary">Create Admin</button>
    </form>
</div>

<?php layout_foot(); ?>
