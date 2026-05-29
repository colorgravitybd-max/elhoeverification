<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Normalizes user-entered codes to the canonical digit-only form.
 * Also classifies obvious junk inputs.
 */
final class CodeNormalizer
{
    private const MIN_LEN = 6;
    private const MAX_LEN = 20;

    /**
     * @return array{ok:bool, normalized:string, raw:string, error:?string}
     */
    public static function normalize(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'normalized' => '', 'raw' => $raw, 'error' => 'Please enter a code.'];
        }

        // Strip URL fragments
        $stripped = preg_replace('#https?://#i', '', $raw) ?? $raw;
        $stripped = preg_replace('#www\.#i', '', $stripped) ?? $stripped;
        // Strip common domains from junk pastes
        $stripped = preg_replace('#elhoe\.com|facebook\.com|wa\.me|instagram\.com#i', '', $stripped) ?? $stripped;

        // Keep digits only
        $digits = preg_replace('/[^0-9]/', '', $stripped) ?? '';

        if ($digits === '') {
            return ['ok' => false, 'normalized' => '', 'raw' => $raw, 'error' => 'Code must contain numbers.'];
        }
        if (strlen($digits) < self::MIN_LEN) {
            return ['ok' => false, 'normalized' => $digits, 'raw' => $raw,
                    'error' => 'Code is too short. Please double-check.'];
        }
        if (strlen($digits) > self::MAX_LEN) {
            return ['ok' => false, 'normalized' => $digits, 'raw' => $raw,
                    'error' => 'Code is too long.'];
        }
        if (self::isObviousJunk($digits)) {
            return ['ok' => false, 'normalized' => $digits, 'raw' => $raw,
                    'error' => 'Please enter the actual code from your product.'];
        }

        return ['ok' => true, 'normalized' => $digits, 'raw' => $raw, 'error' => null];
    }

    private static function isObviousJunk(string $digits): bool
    {
        // All same digit (0000000, 1111111, etc.)
        if (preg_match('/^(\d)\1+$/', $digits)) return true;

        // 1234..., 12345..., 0123..., basic ascending patterns
        if (preg_match('/^0?123456(7(89(0)?)?)?/', $digits)) return true;

        return false;
    }

    /**
     * Levenshtein-based "did you mean" suggestion against existing code list.
     * Keep this list small (<5000) for performance.
     */
    public static function suggest(string $normalized, int $maxResults = 3, int $maxDistance = 2): array
    {
        $rows = Database::all(
            "SELECT code_normalized FROM codes WHERE status = 'active' LIMIT 5000"
        );
        $candidates = [];
        $len = strlen($normalized);
        foreach ($rows as $r) {
            $cand = (string) $r['code_normalized'];
            // Cheap pre-filter on length
            if (abs(strlen($cand) - $len) > $maxDistance) continue;
            $d = levenshtein($normalized, $cand);
            if ($d <= $maxDistance && $d > 0) {
                $candidates[] = ['code' => $cand, 'distance' => $d];
            }
        }
        usort($candidates, fn($a, $b) => $a['distance'] <=> $b['distance']);
        return array_slice(array_map(fn($c) => $c['code'], $candidates), 0, $maxResults);
    }
}
