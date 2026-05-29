<?php
/**
 * Common API bootstrap.
 * Require this from every endpoint to:
 *  - load config + autoloader
 *  - set JSON headers
 *  - parse JSON body into $_POST-style array
 *  - apply CORS for same-origin only
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Helper: read JSON body (or fall back to $_POST)
if (!function_exists('api_input')) {
    function api_input(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j)) return $j;
        }
        return $_POST ?: [];
    }
}

if (!function_exists('api_json')) {
    function api_json($data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, int $status = 400, array $extra = []): void
    {
        api_json(array_merge(['error' => true, 'message' => $message], $extra), $status);
    }
}

if (!function_exists('api_method')) {
    function api_method(string ...$allowed): void
    {
        $m = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($m, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            api_error('Method not allowed.', 405);
        }
    }
}
