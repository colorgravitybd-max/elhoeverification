<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Per-session CSRF tokens. Use token() in forms/JS and validate() in handlers.
 */
final class CSRF
{
    public static function token(): string
    {
        elhoe_start_session();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . e(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        elhoe_start_session();
        $stored = $_SESSION['_csrf'] ?? null;
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    public static function check(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate($token)) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'csrf_invalid', 'message' => 'Session expired. Please refresh and try again.']);
            exit;
        }
    }
}
