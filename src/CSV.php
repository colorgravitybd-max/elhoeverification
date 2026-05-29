<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * CSV read/write helper. UTF-8 with BOM for Excel compatibility on writes.
 */
final class CSV
{
    /** Stream-read a CSV file row-by-row. Yields associative arrays keyed by header. */
    public static function read(string $path): \Generator
    {
        $h = fopen($path, 'r');
        if ($h === false) {
            throw new \RuntimeException("Cannot open CSV: {$path}");
        }
        // Skip BOM
        $first = fread($h, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($h);
        }
        $headers = fgetcsv($h);
        if ($headers === false || $headers === null) {
            fclose($h);
            return;
        }
        $headers = array_map(fn($h) => trim((string) $h), $headers);

        $rowNum = 1;
        while (($row = fgetcsv($h)) !== false) {
            $rowNum++;
            if ($row === [null] || $row === false) continue;
            $assoc = [];
            foreach ($headers as $i => $col) {
                $assoc[$col] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
            yield $rowNum => $assoc;
        }
        fclose($h);
    }

    /**
     * Write CSV directly to PHP output (sets headers).
     * @param string $filename Suggested download filename.
     * @param array $headers   Column headers, in order.
     * @param iterable $rows   Each row = array of values matching headers order, OR assoc array keyed by headers.
     */
    public static function downloadStream(string $filename, array $headers, iterable $rows): void
    {
        // Clean any prior output
        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            if (self::isAssoc($row)) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($out, $line);
            } else {
                fputcsv($out, $row);
            }
        }
        fclose($out);
    }

    /** Write CSV to a file path. */
    public static function writeFile(string $path, array $headers, iterable $rows): void
    {
        $h = fopen($path, 'w');
        if ($h === false) throw new \RuntimeException("Cannot write CSV: {$path}");
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, $headers);
        foreach ($rows as $row) {
            if (self::isAssoc($row)) {
                $line = [];
                foreach ($headers as $col) {
                    $line[] = $row[$col] ?? '';
                }
                fputcsv($h, $line);
            } else {
                fputcsv($h, $row);
            }
        }
        fclose($h);
    }

    private static function isAssoc(array $a): bool
    {
        if ($a === []) return false;
        return array_keys($a) !== range(0, count($a) - 1);
    }
}
