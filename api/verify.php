<?php
/**
 * POST /api/verify.php
 * Body: { "code": "1234567890" }
 * Returns: { result, message, product?, code?, recommendations?, suggestions?, ... }
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use Elhoe\CSRF;
use Elhoe\VerifyService;
use Elhoe\Logger;

api_method('POST');

// CSRF (best-effort: header preferred, body fallback). Customer-facing pages issue tokens.
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$token) {
    $body = api_input();
    $token = $body['_csrf'] ?? null;
}
if (!CSRF::validate(is_string($token) ? $token : null)) {
    // Soft refusal: instead of 419, treat as bad-input. Frontend will reload to refresh CSRF.
    api_error('Session expired. Please refresh the page and try again.', 419);
}

$body = api_input();
$rawCode = (string) ($body['code'] ?? '');

if ($rawCode === '') {
    api_error('Please enter your product code.', 400);
}

try {
    $sessionId = session_id() ?: null;
    $result = VerifyService::verify($rawCode, $sessionId);
    api_json($result, 200);
} catch (\Throwable $e) {
    Logger::error('verify.php fatal: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    api_error('Service temporarily unavailable. Please try again.', 503);
}
