<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Geo lookup via free public HTTPS APIs, results cached in DB for 30 days.
 * Primary: ipwho.is (no key, unlimited)
 * Fallback: ipapi.co (no key, 1000/day)
 *
 * Returns: [country_code, country_name, city, region, latitude, longitude, provider]
 * Any field can be null.
 */
final class GeoIP
{
    public static function lookup(string $ip): array
    {
        $empty = [
            'country_code' => null, 'country_name' => null, 'city' => null,
            'region' => null, 'latitude' => null, 'longitude' => null, 'provider' => null,
        ];

        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return $empty;
        if (self::isPrivateIp($ip)) return $empty;

        // 1) DB cache lookup
        $cacheDays = (int) env('GEOIP_CACHE_DAYS', 30);
        $cached = Database::one(
            "SELECT country_code, country_name, city, region, latitude, longitude, provider
             FROM ip_geo_cache
             WHERE ip_address = :ip AND cached_at > DATE_SUB(NOW(), INTERVAL :d DAY) LIMIT 1",
            [':ip' => $ip, ':d' => $cacheDays]
        );
        if ($cached) return $cached;

        // 2) Primary
        $primary = (string) env('GEOIP_PROVIDER', 'ipwho.is');
        $data = self::fetchFrom($primary, $ip);

        // 3) Fallback if primary failed
        if ($data === null) {
            $fallback = (string) env('GEOIP_FALLBACK', 'ipapi.co');
            if ($fallback !== $primary) {
                $data = self::fetchFrom($fallback, $ip);
            }
        }

        if ($data === null) {
            return $empty;
        }

        // 4) Cache
        try {
            Database::exec(
                "INSERT INTO ip_geo_cache (ip_address, country_code, country_name, city, region, latitude, longitude, provider)
                 VALUES (:ip, :cc, :cn, :ci, :rg, :la, :lo, :pr)
                 ON DUPLICATE KEY UPDATE
                   country_code = VALUES(country_code),
                   country_name = VALUES(country_name),
                   city         = VALUES(city),
                   region       = VALUES(region),
                   latitude     = VALUES(latitude),
                   longitude    = VALUES(longitude),
                   provider     = VALUES(provider),
                   cached_at    = NOW()",
                [
                    ':ip' => $ip,
                    ':cc' => $data['country_code'],
                    ':cn' => $data['country_name'],
                    ':ci' => $data['city'],
                    ':rg' => $data['region'],
                    ':la' => $data['latitude'],
                    ':lo' => $data['longitude'],
                    ':pr' => $data['provider'],
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('GeoIP cache insert failed: ' . $e->getMessage());
        }

        return $data;
    }

    private static function fetchFrom(string $provider, string $ip): ?array
    {
        $url = match ($provider) {
            'ipwho.is' => "https://ipwho.is/{$ip}",
            'ipapi.co' => "https://ipapi.co/{$ip}/json/",
            default    => null,
        };
        if ($url === null) return null;

        $raw = self::httpGet($url, 3);
        if ($raw === null) return null;

        $j = json_decode($raw, true);
        if (!is_array($j)) return null;

        if ($provider === 'ipwho.is') {
            if (($j['success'] ?? false) !== true) return null;
            return [
                'country_code' => self::s($j, 'country_code'),
                'country_name' => self::s($j, 'country'),
                'city'         => self::s($j, 'city'),
                'region'       => self::s($j, 'region'),
                'latitude'     => isset($j['latitude'])  ? (float) $j['latitude']  : null,
                'longitude'    => isset($j['longitude']) ? (float) $j['longitude'] : null,
                'provider'     => 'ipwho.is',
            ];
        }
        if ($provider === 'ipapi.co') {
            if (!empty($j['error'])) return null;
            return [
                'country_code' => self::s($j, 'country_code'),
                'country_name' => self::s($j, 'country_name'),
                'city'         => self::s($j, 'city'),
                'region'       => self::s($j, 'region'),
                'latitude'     => isset($j['latitude'])  ? (float) $j['latitude']  : null,
                'longitude'    => isset($j['longitude']) ? (float) $j['longitude'] : null,
                'provider'     => 'ipapi.co',
            ];
        }
        return null;
    }

    private static function httpGet(string $url, int $timeout = 3): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'ELHOE-Verification/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res === false || $code < 200 || $code >= 300) return null;
            return (string) $res;
        }
        // Fallback to file_get_contents with stream context
        $ctx = stream_context_create([
            'http'  => ['timeout' => $timeout, 'header' => "User-Agent: ELHOE-Verification/1.0\r\n"],
            'https' => ['timeout' => $timeout, 'header' => "User-Agent: ELHOE-Verification/1.0\r\n"],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : $res;
    }

    private static function s(array $j, string $k): ?string
    {
        if (!isset($j[$k])) return null;
        $v = $j[$k];
        return is_string($v) && $v !== '' ? $v : null;
    }

    private static function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
