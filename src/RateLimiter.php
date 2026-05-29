<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Simple per-IP rate limiter using `rate_limits` table.
 * window_seconds = how often the bucket resets.
 */
final class RateLimiter
{
    /**
     * @return array{allowed:bool, count:int, limit:int, retry_after_seconds:int}
     */
    public static function hit(string $ip, string $bucket, int $limit, int $windowSeconds): array
    {
        $now = time();
        $row = Database::one(
            "SELECT count, UNIX_TIMESTAMP(window_start) AS ws
             FROM rate_limits WHERE ip_address = :ip AND bucket = :b LIMIT 1",
            [':ip' => $ip, ':b' => $bucket]
        );

        if (!$row) {
            Database::exec(
                "INSERT INTO rate_limits (ip_address, bucket, count, window_start)
                 VALUES (:ip, :b, 1, NOW())
                 ON DUPLICATE KEY UPDATE count = 1, window_start = NOW()",
                [':ip' => $ip, ':b' => $bucket]
            );
            return ['allowed' => true, 'count' => 1, 'limit' => $limit, 'retry_after_seconds' => 0];
        }

        $windowStart = (int) $row['ws'];
        $count = (int) $row['count'];

        if ($now - $windowStart > $windowSeconds) {
            // window expired, reset
            Database::exec(
                "UPDATE rate_limits SET count = 1, window_start = NOW()
                 WHERE ip_address = :ip AND bucket = :b",
                [':ip' => $ip, ':b' => $bucket]
            );
            return ['allowed' => true, 'count' => 1, 'limit' => $limit, 'retry_after_seconds' => 0];
        }

        if ($count >= $limit) {
            $retry = $windowSeconds - ($now - $windowStart);
            return ['allowed' => false, 'count' => $count, 'limit' => $limit, 'retry_after_seconds' => max(1, $retry)];
        }

        Database::exec(
            "UPDATE rate_limits SET count = count + 1
             WHERE ip_address = :ip AND bucket = :b",
            [':ip' => $ip, ':b' => $bucket]
        );

        return ['allowed' => true, 'count' => $count + 1, 'limit' => $limit, 'retry_after_seconds' => 0];
    }

    public static function cleanup(int $olderThanHours = 24): int
    {
        return Database::exec(
            "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL :h HOUR)",
            [':h' => $olderThanHours]
        )->rowCount();
    }
}
