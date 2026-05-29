<?php
/**
 * ELHOE Verification - central config loader
 *
 * Loads .env, defines paths, sets PHP runtime options, registers autoloader.
 * Every entry point (public/index.php, admin/*, api/*) must require this first.
 */

declare(strict_types=1);

// ----- Hard paths (relative to this file) ---------------------------------
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
define('SRC_PATH', APP_ROOT . '/src');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('CONFIG_PATH', APP_ROOT . '/config');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('ADMIN_PATH', APP_ROOT . '/admin');
define('API_PATH', APP_ROOT . '/api');
define('MIGRATIONS_PATH', APP_ROOT . '/migrations');

// ----- Load .env ----------------------------------------------------------
$envFile = CONFIG_PATH . '/.env';
if (!file_exists($envFile)) {
    http_response_code(500);
    if (file_exists(CONFIG_PATH . '/.env.example')) {
        die('Configuration error: config/.env not found. Copy config/.env.example to config/.env and edit.');
    }
    die('Configuration error: config/.env not found.');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (!str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    // Strip surrounding quotes
    if (strlen($v) >= 2 && (
        ($v[0] === '"' && $v[-1] === '"') ||
        ($v[0] === "'" && $v[-1] === "'")
    )) {
        $v = substr($v, 1, -1);
    }
    if (!isset($_ENV[$k])) {
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}

// ----- env helper --------------------------------------------------------
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        $low = strtolower((string)$v);
        if ($low === 'true')  return true;
        if ($low === 'false') return false;
        if ($low === 'null')  return null;
        return $v;
    }
}

// ----- Runtime ------------------------------------------------------------
date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));
mb_internal_encoding('UTF-8');

if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
}

// ----- Autoloader (manual PSR-4) -----------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'Elhoe\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $rel = str_replace('\\', '/', $rel);
    $file = SRC_PATH . '/' . $rel . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// ----- Session config (only when needed) ---------------------------------
if (!function_exists('elhoe_start_session')) {
    function elhoe_start_session(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name((string) env('SESSION_NAME', 'elhoe_admin_sess'));
        session_set_cookie_params([
            'lifetime' => (int) env('SESSION_LIFETIME_MINUTES', 120) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) env('COOKIE_SECURE', true),
            'httponly' => true,
            'samesite' => (string) env('COOKIE_SAMESITE', 'Lax'),
        ]);
        session_start();
    }
}

// ----- Tiny path helpers --------------------------------------------------
if (!function_exists('storage_path')) {
    function storage_path(string $sub = ''): string {
        return STORAGE_PATH . ($sub === '' ? '' : '/' . ltrim($sub, '/'));
    }
}
if (!function_exists('public_url')) {
    function public_url(string $path = ''): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base . '/admin' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
if (!function_exists('api_url')) {
    function api_url(string $path = ''): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base . '/api' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base . '/assets/' . ltrim($path, '/');
    }
}
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
