<?php
/**
 * POST /api/suggest.php
 * Body: { "code": "1234567890" }
 * Returns: { suggestions: [...] }
 *
 * Stand-alone suggestion endpoint for autocomplete-style helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use Elhoe\Auth;
use Elhoe\CodeNormalizer;
use Elhoe\RateLimiter;

api_method('POST');

$ip = Auth::ip();
$rl = RateLimiter::hit($ip, 'suggest', 30, 60);
if (!$rl['allowed']) {
    api_error('Too many requests.', 429, ['retry_after_seconds' => $rl['retry_after_seconds']]);
}

$body = api_input();
$raw  = (string) ($body['code'] ?? '');
$n = CodeNormalizer::normalize($raw);
if (!$n['ok']) {
    api_json(['suggestions' => []]);
}
api_json(['suggestions' => CodeNormalizer::suggest($n['normalized'])]);
