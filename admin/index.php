<?php
/**
 * Admin login page (also entry point: /checker/admin/).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Settings;

elhoe_start_session();

// If already logged in, go to dashboard
if (Auth::check()) {
    header('Location: ' . admin_url('dashboard.php'));
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['_csrf'] ?? null)) {
        $error = 'Session expired. Please refresh and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $r = Auth::attempt($username, $password);
            if ($r['ok']) {
                $next = (string) ($_GET['next'] ?? admin_url('dashboard.php'));
                if (!preg_match('#^https?://#i', $next)) {
                    header('Location: ' . $next);
                } else {
                    header('Location: ' . admin_url('dashboard.php'));
                }
                exit;
            }
            $error = $r['error'] ?? 'Login failed.';
        }
    }
}

$brand = Settings::get('brand_name', 'ELHOE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e($brand) ?> Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>?v=1">
</head>
<body class="admin admin-login">

<div class="login-wrap">
    <div class="login-card">
        <h1 class="login-brand"><?= e($brand) ?></h1>
        <p class="login-subtitle">Sign in to the verification admin</p>

        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <?= CSRF::field() ?>
            <div class="field">
                <label for="username">Username or email</label>
                <input id="username" name="username" type="text" autocomplete="username" value="<?= e($username) ?>" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </div>
    <p class="login-footer">© <?= date('Y') ?> <?= e($brand) ?></p>
</div>

</body>
</html>
