<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Admin authentication: login, lockout, session-based.
 */
final class Auth
{
    public static function attempt(string $username, string $password): array
    {
        elhoe_start_session();

        $user = Database::one(
            "SELECT * FROM admin_users WHERE username = :u OR email = :u LIMIT 1",
            [':u' => $username]
        );

        if (!$user) {
            self::sleepRandom();
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        if ($user['status'] === 'disabled') {
            return ['ok' => false, 'error' => 'Account disabled.'];
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $remaining = (int) ceil((strtotime((string) $user['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Account locked. Try again in {$remaining} minute(s)."];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            self::onFailure((int) $user['id']);
            self::sleepRandom();
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        // Success - reset counters, set session
        Database::exec(
            "UPDATE admin_users SET failed_attempts = 0, locked_until = NULL,
             last_login_at = NOW(), last_login_ip = :ip WHERE id = :id",
            [':ip' => self::ip(), ':id' => $user['id']]
        );

        session_regenerate_id(true);
        $_SESSION['admin_id']       = (int) $user['id'];
        $_SESSION['admin_username'] = (string) $user['username'];
        $_SESSION['admin_role']     = (string) $user['role'];
        $_SESSION['admin_login_at'] = time();

        AuditLog::record('admin_login', 'admin_user', (int) $user['id'], ['username' => $user['username']]);

        return ['ok' => true, 'user' => $user];
    }

    public static function user(): ?array
    {
        elhoe_start_session();
        $id = $_SESSION['admin_id'] ?? null;
        if (!$id) return null;
        return Database::one("SELECT id, username, email, role, status FROM admin_users WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    public static function check(): bool
    {
        $u = self::user();
        return is_array($u) && $u['status'] === 'active';
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u || $u['status'] !== 'active') {
            if (self::isJsonRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'unauthorized']);
                exit;
            }
            $next = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . admin_url('?next=' . urlencode($next)));
            exit;
        }
        return $u;
    }

    public static function requireRole(string ...$roles): array
    {
        $u = self::require();
        if (!in_array($u['role'], $roles, true)) {
            http_response_code(403);
            die('Forbidden.');
        }
        return $u;
    }

    public static function logout(): void
    {
        elhoe_start_session();
        $u = self::user();
        if ($u) {
            AuditLog::record('admin_logout', 'admin_user', (int) $u['id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    private static function onFailure(int $userId): void
    {
        $maxAttempts = (int) env('ADMIN_LOGIN_ATTEMPTS', 5);
        $lockMinutes = (int) env('ADMIN_LOGIN_LOCKOUT_MINUTES', 15);

        Database::exec(
            "UPDATE admin_users SET failed_attempts = failed_attempts + 1,
             locked_until = CASE WHEN failed_attempts + 1 >= :max THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE) ELSE locked_until END
             WHERE id = :id",
            [':max' => $maxAttempts, ':mins' => $lockMinutes, ':id' => $userId]
        );
    }

    public static function ip(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private static function sleepRandom(): void
    {
        usleep(random_int(150_000, 400_000));
    }

    private static function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ct     = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($ct, 'application/json');
    }
}
