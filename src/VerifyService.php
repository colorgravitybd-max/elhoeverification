<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Core verification orchestrator.
 * Owns the decision tree and writes to scan_logs.
 */
final class VerifyService
{
    /**
     * Verify a code. Always logs to scan_logs.
     * @return array Result envelope with: result, code, product, suggestions, ...
     */
    public static function verify(string $rawInput, ?string $sessionId = null): array
    {
        $ip = Auth::ip();
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        // 1. Rate limit
        $perMin = (int) env('RATE_LIMIT_VERIFY_PER_MIN', 10);
        $rl = RateLimiter::hit($ip, 'verify', $perMin, 60);
        if (!$rl['allowed']) {
            self::log($rawInput, '', null, null, 'rate_limited', $ip, $ua, $ref, $sessionId);
            return [
                'result' => 'rate_limited',
                'message' => 'Too many attempts. Please wait a moment and try again.',
                'retry_after_seconds' => $rl['retry_after_seconds'],
            ];
        }

        // 2. Normalize
        $n = CodeNormalizer::normalize($rawInput);
        if (!$n['ok']) {
            self::log($rawInput, $n['normalized'], null, null, 'malformed', $ip, $ua, $ref, $sessionId);
            return [
                'result'  => 'malformed',
                'message' => $n['error'] ?? 'Invalid code.',
            ];
        }
        $normalized = $n['normalized'];

        // 3. Lookup
        $code = CodeService::findByNormalized($normalized);
        if (!$code) {
            $suggestions = CodeNormalizer::suggest($normalized);
            self::log($rawInput, $normalized, null, null, 'invalid', $ip, $ua, $ref, $sessionId);

            // Fire counterfeit/fail tracking
            self::dispatchClientFailEvent('invalid', $normalized);
            PixelDispatcher::fireServerEvent('Search', [
                'search_string' => $normalized,
                'content_category' => 'verification_invalid',
            ]);

            return [
                'result'  => 'invalid',
                'message' => 'This code was not found in our records.',
                'submitted_code' => $normalized,
                'suggestions' => $suggestions,
            ];
        }

        // 4. Status check
        if ($code['status'] === 'quarantined') {
            self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'quarantined', $ip, $ua, $ref, $sessionId);
            self::dispatchClientFailEvent('quarantined', $normalized);
            return [
                'result'  => 'quarantined',
                'message' => 'This code has been flagged for suspicious activity. Please contact our support.',
                'support_email' => Settings::get('support_email', 'support@elhoe.com'),
                'support_whatsapp' => Settings::get('support_whatsapp', ''),
            ];
        }
        if ($code['status'] === 'inactive') {
            self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'invalid', $ip, $ua, $ref, $sessionId);
            return [
                'result' => 'invalid',
                'message' => 'This code is no longer active.',
            ];
        }

        // 5. Mode-specific decision
        $product  = ProductService::find((int) $code['product_id']);
        $isFirstScan = empty($code['first_scanned_at']);

        if ($code['mode'] === 'universal') {
            self::touchScan($code, $ip, $isFirstScan);
            self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'valid_universal', $ip, $ua, $ref, $sessionId);
            // Recompute risk after scan
            RiskScorer::recomputeForCode((int) $code['id']);
            $payload = [
                'result'  => 'valid_universal',
                'message' => 'This is a genuine ELHOE product.',
                'product' => self::productPayload($product),
                'code'    => self::codePayload($code),
                'recommendations' => self::buildRecommendations($code, $product),
            ];
            self::dispatchClientSuccessEvent('valid_universal', $product);
            PixelDispatcher::fireServerEvent('ViewContent', [
                'content_name' => $product['name'] ?? '',
                'content_ids'  => [$product['sku'] ?? ($product['id'] ?? '')],
                'content_type' => 'product',
                'content_category' => 'verification_universal',
            ]);
            return $payload;
        }

        // mode = unique
        if ($isFirstScan) {
            self::touchScan($code, $ip, true);
            self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'valid_unique_first', $ip, $ua, $ref, $sessionId);
            $payload = [
                'result'  => 'valid_unique_first',
                'message' => 'Genuine product! Register to activate your warranty.',
                'product' => self::productPayload($product),
                'code'    => self::codePayload($code),
                'recommendations' => self::buildRecommendations($code, $product),
                'register_required' => true,
            ];
            self::dispatchClientSuccessEvent('valid_unique_first', $product);
            PixelDispatcher::fireServerEvent('ViewContent', [
                'content_name' => $product['name'] ?? '',
                'content_ids'  => [$product['sku'] ?? ($product['id'] ?? '')],
                'content_type' => 'product',
                'content_category' => 'verification_first',
            ]);
            return $payload;
        }

        if (!empty($code['owner_customer_id'])) {
            $customer = Database::one("SELECT first_name, last_name, email, registered_at FROM customers WHERE id = :id LIMIT 1", [':id' => $code['owner_customer_id']]);
            self::touchScan($code, $ip, false);
            self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'already_registered', $ip, $ua, $ref, $sessionId);
            RiskScorer::recomputeForCode((int) $code['id']);

            $payload = [
                'result'  => 'already_registered',
                'message' => 'Welcome back! This product is already registered.',
                'product' => self::productPayload($product),
                'code'    => self::codePayload($code),
                'owner'   => $customer ? [
                    'first_name'    => $customer['first_name'] ?? '',
                    'registered_at' => $customer['registered_at'] ?? '',
                ] : null,
                'recommendations' => self::buildRecommendations($code, $product),
            ];
            self::dispatchClientSuccessEvent('already_registered', $product);
            return $payload;
        }

        // unique scanned multiple times but never registered - returning, not yet linked
        self::touchScan($code, $ip, false);
        self::log($rawInput, $normalized, (int) $code['id'], (int) $code['product_id'], 'valid_unique_returning', $ip, $ua, $ref, $sessionId);
        RiskScorer::recomputeForCode((int) $code['id']);

        $payload = [
            'result'  => 'valid_unique_returning',
            'message' => 'Genuine product. Complete your registration to activate the warranty.',
            'product' => self::productPayload($product),
            'code'    => self::codePayload($code),
            'register_required' => true,
            'recommendations' => self::buildRecommendations($code, $product),
        ];
        self::dispatchClientSuccessEvent('valid_unique_returning', $product);
        return $payload;
    }

    /**
     * Bind owner to a unique code (first registration). Idempotent.
     */
    public static function registerOwner(int $codeId, array $customerData): array
    {
        $code = CodeService::find($codeId);
        if (!$code) return ['ok' => false, 'message' => 'Invalid code.'];
        if ($code['mode'] !== 'unique') return ['ok' => false, 'message' => 'This code is not eligible for registration.'];
        if (!empty($code['owner_customer_id'])) {
            $cust = Database::one("SELECT first_name FROM customers WHERE id = :id", [':id' => $code['owner_customer_id']]);
            return ['ok' => true, 'message' => 'Already registered.', 'owner' => $cust];
        }

        $email = trim((string) ($customerData['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'A valid email is required.'];
        }

        $customerId = CustomerService::register([
            'email'              => $email,
            'phone'              => $customerData['phone']      ?? '',
            'first_name'         => $customerData['first_name'] ?? '',
            'last_name'          => $customerData['last_name']  ?? '',
            'city'               => $customerData['city']       ?? '',
            'country'            => $customerData['country']    ?? '',
            'consent_marketing'  => $customerData['consent_marketing'] ?? 0,
            'first_product_id'   => (int) $code['product_id'],
            'first_code_id'      => (int) $code['id'],
            'registered_ip'      => Auth::ip(),
        ]);

        Database::exec(
            "UPDATE codes SET owner_customer_id = :cid, registered_at = NOW(), updated_at = NOW() WHERE id = :id",
            [':cid' => $customerId, ':id' => $codeId]
        );

        // Fire Lead events
        PixelDispatcher::fireServerEvent('Lead', [
            'content_name'     => 'Product Registration',
            'content_category' => 'Registration',
        ], [
            'email'      => $email,
            'phone'      => $customerData['phone']      ?? '',
            'first_name' => $customerData['first_name'] ?? '',
            'last_name'  => $customerData['last_name']  ?? '',
            'city'       => $customerData['city']       ?? '',
            'country'    => $customerData['country']    ?? '',
        ]);

        // Email admin (best-effort; failures don't block the registration)
        try {
            $product = ProductService::find((int) $code['product_id']);
            EmailNotifier::notifyRegistration([
                'customer' => [
                    'first_name'        => $customerData['first_name'] ?? '',
                    'last_name'         => $customerData['last_name']  ?? '',
                    'email'             => $email,
                    'phone'             => $customerData['phone']      ?? '',
                    'city'              => $customerData['city']       ?? '',
                    'consent_marketing' => $customerData['consent_marketing'] ?? 0,
                ],
                'product' => $product,
                'code'    => $code,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Registration notify failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'message' => 'Registration successful.', 'customer_id' => $customerId];
    }

    private static function touchScan(array $code, string $ip, bool $isFirst): void
    {
        $set = "scan_count = scan_count + 1, last_scanned_at = NOW(), updated_at = NOW()";
        $params = [':id' => $code['id']];
        if ($isFirst) {
            $set .= ", first_scanned_at = NOW(), first_scan_ip = :ip";
            $params[':ip'] = $ip;
        }
        Database::exec("UPDATE codes SET {$set} WHERE id = :id", $params);
    }

    private static function log(
        string $rawInput, string $normalized, ?int $codeId, ?int $productId,
        string $result, string $ip, string $ua, string $referrer, ?string $sessionId
    ): void {
        $geo = ['country_code' => null, 'country_name' => null, 'city' => null];
        try {
            $geo = GeoIP::lookup($ip);
        } catch (\Throwable $e) {
            Logger::warning('GeoIP lookup failed: ' . $e->getMessage());
        }
        Database::insert('scan_logs', [
            'code_input'     => substr($rawInput, 0, 255),
            'code_normalized' => $normalized,
            'code_id'        => $codeId,
            'product_id'     => $productId,
            'result'         => $result,
            'ip_address'     => $ip,
            'ip_country'     => $geo['country_code'] ?? null,
            'ip_country_name' => $geo['country_name'] ?? null,
            'ip_city'        => $geo['city'] ?? null,
            'user_agent'     => substr($ua, 0, 500),
            'referrer'       => substr($referrer, 0, 500),
            'session_id'     => $sessionId,
        ]);
    }

    private static function productPayload(?array $p): ?array
    {
        if (!$p) return null;
        return [
            'id'          => (int) $p['id'],
            'name'        => $p['name'],
            'sku'         => $p['sku']         ?? null,
            'image_url'   => $p['image_url']   ?? null,
            'product_url' => $p['product_url'] ?? null,
            'description' => $p['description'] ?? null,
        ];
    }

    private static function codePayload(array $c): array
    {
        return [
            'id'           => (int) $c['id'],
            'code'         => $c['code'],
            'mode'         => $c['mode'],
            'batch_number' => $c['batch_number'] ?? null,
            'expiry_date'  => $c['expiry_date']  ?? null,
            'scan_count'   => (int) $c['scan_count'],
        ];
    }

    private static function buildRecommendations(array $code, ?array $product): array
    {
        // 1. Use code's recommended_product_ids if set
        if (!empty($code['recommended_product_ids'])) {
            $ids = array_filter(array_map('intval', explode(',', (string) $code['recommended_product_ids'])));
            if ($ids) {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $rows = Database::all("SELECT id, name, image_url, product_url FROM products WHERE id IN ({$place}) AND status = 'active' LIMIT 3", $ids);
                return array_map([self::class, 'productPayload'], $rows);
            }
        }
        // 2. Fall back to routine_group siblings
        if ($product && !empty($product['routine_group'])) {
            $rows = Database::all(
                "SELECT id, name, image_url, product_url FROM products
                 WHERE routine_group = :rg AND id <> :pid AND status = 'active'
                 ORDER BY RAND() LIMIT 3",
                [':rg' => $product['routine_group'], ':pid' => $product['id']]
            );
            return array_map([self::class, 'productPayload'], $rows);
        }
        return [];
    }

    private static function dispatchClientSuccessEvent(string $scenario, ?array $product): void
    {
        // The result page renders these via JS using GTMHelper::pushEventJs
        // No-op here; orchestration left to result.php for client-side dispatching.
    }

    private static function dispatchClientFailEvent(string $scenario, string $code): void
    {
        // Same: rendered on result.php
    }

    /**
     * Public hook used by api/verify.php right after a verification result
     * is built. Centralised here so the notifier doesn't fire for internal
     * test calls.
     */
    public static function notifyAdmin(array $result, string $rawInput): void
    {
        try {
            $geo = ['country_name' => null, 'city' => null];
            // Re-use last logged scan_log row to get the resolved geo (cheaper
            // than another GeoIP lookup; row was inserted milliseconds ago).
            $row = Database::one(
                "SELECT ip_address, ip_country_name, ip_city, user_agent
                 FROM scan_logs ORDER BY id DESC LIMIT 1"
            );
            EmailNotifier::notifyScan([
                'result'     => $result['result']  ?? '',
                'code_input' => $rawInput,
                'code'       => $result['code']    ?? null,
                'product'    => $result['product'] ?? null,
                'ip'         => $row['ip_address']      ?? Auth::ip(),
                'city'       => $row['ip_city']         ?? '',
                'country'    => $row['ip_country_name'] ?? '',
                'user_agent' => $row['user_agent']      ?? '',
            ]);
        } catch (\Throwable $e) {
            Logger::warning('VerifyService::notifyAdmin failed: ' . $e->getMessage());
        }
    }
}
