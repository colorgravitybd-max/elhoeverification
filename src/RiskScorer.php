<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Risk scoring for counterfeit detection.
 * Recomputes score (0-100) based on scan_logs patterns.
 */
final class RiskScorer
{
    public static function recomputeForCode(int $codeId): int
    {
        $code = Database::one("SELECT * FROM codes WHERE id = :id LIMIT 1", [':id' => $codeId]);
        if (!$code) return 0;

        $score = 0;

        // Base count of distinct IPs in last 24h
        $ips24 = (int) Database::scalar(
            "SELECT COUNT(DISTINCT ip_address) FROM scan_logs
             WHERE code_id = :id AND scanned_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [':id' => $codeId]
        );

        $countries24 = (int) Database::scalar(
            "SELECT COUNT(DISTINCT ip_country) FROM scan_logs
             WHERE code_id = :id AND ip_country IS NOT NULL
             AND scanned_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [':id' => $codeId]
        );

        // Velocity: scans in last minute
        $perMinute = (int) Database::scalar(
            "SELECT COUNT(*) FROM scan_logs
             WHERE code_id = :id AND scanned_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [':id' => $codeId]
        );

        // Total scan_count vs mode
        $scanCount = (int) $code['scan_count'];

        // Rule: unique mode should rarely be scanned > 1 time
        if ($code['mode'] === 'unique' && $scanCount > 1) {
            $score += 30 + min(30, ($scanCount - 1) * 5);
        }

        if ($ips24 > 5)        $score += 20;
        if ($countries24 > 3)  $score += 15;
        if ($perMinute >= 5)   $score += 15;

        // Universal abuse: 1000+ scans is suspicious for single product
        if ($code['mode'] === 'universal' && $scanCount > 1000) {
            $score += 10 + min(20, intdiv($scanCount - 1000, 500) * 5);
        }

        $score = max(0, min(100, $score));

        $newStatus = $code['status'];
        if ($score >= 70 && $code['status'] === 'active') {
            $newStatus = 'quarantined';
        }

        Database::exec(
            "UPDATE codes SET risk_score = :s, status = :st, updated_at = NOW() WHERE id = :id",
            [':s' => $score, ':st' => $newStatus, ':id' => $codeId]
        );

        if ($newStatus === 'quarantined' && $code['status'] !== 'quarantined') {
            Logger::warning("Auto-quarantined code id={$codeId} score={$score}");
            // TODO: email alert (queued)
        }

        return $score;
    }
}
