<?php
/**
 * Seed an initial admin user via CLI.
 * Run from server (SSH) or hPanel cron once:
 *   php tools/seed-admin.php
 *
 * Defaults: admin / ChangeMe123! / admin@elhoe.com  (super_admin)
 *
 * Optionally pass values:
 *   php tools/seed-admin.php myuser SuperSecret myemail@elhoe.com
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This tool can only be run from the command line.\n");
}

use Elhoe\Database;

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'ChangeMe123!';
$email    = $argv[3] ?? 'admin@elhoe.com';

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

try {
    $existing = Database::one("SELECT id FROM admin_users WHERE username = :u OR email = :e LIMIT 1",
        [':u' => $username, ':e' => $email]);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($existing) {
        Database::exec(
            "UPDATE admin_users SET email = :e, password_hash = :h, role = 'super_admin', status = 'active', failed_attempts = 0, locked_until = NULL WHERE id = :id",
            [':e' => $email, ':h' => $hash, ':id' => $existing['id']]
        );
        echo "Updated existing admin '{$username}' (id={$existing['id']})\n";
    } else {
        $id = Database::insert('admin_users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $hash,
            'role'          => 'super_admin',
            'status'        => 'active',
        ]);
        echo "Created admin '{$username}' (id={$id})\n";
    }
    echo "Login URL: " . admin_url() . "\n";
    echo "Username:  {$username}\n";
    echo "Email:     {$email}\n";
    echo "Password:  {$password}\n";
    echo "\n*** CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN ***\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
