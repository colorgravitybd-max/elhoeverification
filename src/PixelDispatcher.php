<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Server-side Meta Conversions API dispatcher.
 * Pixel client-side is rendered directly in HTML; CAPI mirrors events server-side
 * for higher match rate and ad-blocker resistance.
 *
 * Settings keys used: meta_pixel_id, meta_capi_token, meta_capi_test_code
 */
final class PixelDispatcher
{
    private const GRAPH_VERSION = 'v19.0';

    public static function fireServerEvent(string $eventName, array $eventData = [], array $userData = []): bool
    {
        $pixelId = Settings::get('meta_pixel_id');
        $token   = Settings::get('meta_capi_token');
        if ($pixelId === '' || $token === '') {
            return false; // not configured, silent
        }

        $payload = [
            'data' => [[
                'event_name'       => $eventName,
                'event_time'       => time(),
                'action_source'    => 'website',
                'event_source_url' => self::currentUrl(),
                'event_id'         => bin2hex(random_bytes(8)),
                'user_data'        => self::buildUserData($userData),
                'custom_data'      => $eventData,
            ]],
        ];

        $testCode = Settings::get('meta_capi_test_code');
        if ($testCode !== '') {
            $payload['test_event_code'] = $testCode;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            self::GRAPH_VERSION,
            urlencode($pixelId),
            urlencode($token)
        );

        $ok = self::postJson($url, $payload);
        if (!$ok) {
            Logger::warning('Meta CAPI dispatch failed', ['event' => $eventName]);
        }
        return $ok;
    }

    private static function buildUserData(array $extra): array
    {
        $u = [];
        $ip = Auth::ip();
        if ($ip && $ip !== '0.0.0.0') $u['client_ip_address'] = $ip;
        if (!empty($_SERVER['HTTP_USER_AGENT'])) $u['client_user_agent'] = (string) $_SERVER['HTTP_USER_AGENT'];

        // fbp/fbc cookies (set by Pixel)
        if (!empty($_COOKIE['_fbp'])) $u['fbp'] = (string) $_COOKIE['_fbp'];
        if (!empty($_COOKIE['_fbc'])) $u['fbc'] = (string) $_COOKIE['_fbc'];

        // Hashed PII
        if (!empty($extra['email'])) {
            $u['em'] = [hash('sha256', strtolower(trim((string) $extra['email'])))];
        }
        if (!empty($extra['phone'])) {
            $phone = preg_replace('/\D/', '', (string) $extra['phone']) ?? '';
            if ($phone !== '') $u['ph'] = [hash('sha256', $phone)];
        }
        if (!empty($extra['first_name'])) {
            $u['fn'] = [hash('sha256', strtolower(trim((string) $extra['first_name'])))];
        }
        if (!empty($extra['last_name'])) {
            $u['ln'] = [hash('sha256', strtolower(trim((string) $extra['last_name'])))];
        }
        if (!empty($extra['city'])) {
            $u['ct'] = [hash('sha256', strtolower(preg_replace('/\s+/', '', (string) $extra['city']) ?? ''))];
        }
        if (!empty($extra['country'])) {
            $u['country'] = [hash('sha256', strtolower(trim((string) $extra['country'])))];
        }
        return $u;
    }

    private static function postJson(string $url, array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) return false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $res  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $res !== false && $code >= 200 && $code < 300;
        }
        $ctx = stream_context_create([
            'http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body, 'timeout' => 4],
            'https'=> ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body, 'timeout' => 4],
        ]);
        $r = @file_get_contents($url, false, $ctx);
        return $r !== false;
    }

    private static function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'elhoe.com';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Render the client-side Meta Pixel base code (call from <head>).
     */
    public static function renderPixelHead(): string
    {
        $id = Settings::get('meta_pixel_id');
        if ($id === '') return '';
        $idJs = json_encode($id);
        return <<<HTML
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', {$idJs});
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1"/></noscript>
<!-- End Meta Pixel -->
HTML;
    }

    /** Inline JS to fire a custom event on the client. */
    public static function clientFireJs(string $eventName, array $params = []): string
    {
        $id = Settings::get('meta_pixel_id');
        if ($id === '') return '';
        $name = json_encode($eventName);
        $p    = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return "<script>if(window.fbq){fbq('track', {$name}, {$p});}</script>";
    }
}
