<?php
/**
 * POST /api/register.php
 * Body: { code_id, first_name, last_name, email, phone, city, consent_marketing }
 * Returns: { ok, message, customer_id? }
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use Elhoe\Auth;
use Elhoe\CSRF;
use Elhoe\Logger;
use Elhoe\RateLimiter;
use Elhoe\VerifyService;

api_method('POST');

// CSRF
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$token) {
    $body = api_input();
    $token = $body['_csrf'] ?? null;
}
if (!CSRF::validate(is_string($token) ? $token : null)) {
    api_error('Session expired. Please refresh and try again.', 419);
}

// Rate limit registrations per IP (5/hour)
$ip = Auth::ip();
$perHour = (int) env('RATE_LIMIT_REGISTER_PER_HOUR', 5);
$rl = RateLimiter::hit($ip, 'register', $perHour, 3600);
if (!$rl['allowed']) {
    api_error('Too many registration attempts. Please try again later.', 429, [
        'retry_after_seconds' => $rl['retry_after_seconds'],
    ]);
}

$body = api_input();
$codeId = (int) ($body['code_id'] ?? 0);
if ($codeId <= 0) {
    api_error('Missing code reference.', 400);
}

$email = trim((string) ($body['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('A valid email is required.', 400);
}

try {
    $res = VerifyService::registerOwner($codeId, [
        'first_name'        => (string) ($body['first_name'] ?? ''),
        'last_name'         => (string) ($body['last_name']  ?? ''),
        'email'             => $email,
        'phone'             => (string) ($body['phone']      ?? ''),
        'city'              => (string) ($body['city']       ?? ''),
        'country'           => (string) ($body['country']    ?? ''),
        'consent_marketing' => !empty($body['consent_marketing']) ? 1 : 0,
    ]);

    if (!$res['ok']) {
        api_error($res['message'] ?? 'Registration failed.', 400);
    }

    api_json([
        'ok'          => true,
        'message'     => $res['message'] ?? 'Registered.',
        'customer_id' => $res['customer_id'] ?? null,
    ], 200);
} catch (\Throwable $e) {
    Logger::error('register.php fatal: ' . $e->getMessage());
    api_error('Service temporarily unavailable. Please try again.', 503);
}
