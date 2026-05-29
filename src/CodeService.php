<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Code generation, CSV import, listing, CRUD.
 */
final class CodeService
{
    public static function find(int $id): ?array
    {
        return Database::one("SELECT * FROM codes WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    public static function findByNormalized(string $normalized): ?array
    {
        if ($normalized === '') return null;
        return Database::one("SELECT * FROM codes WHERE code_normalized = :c LIMIT 1", [':c' => $normalized]);
    }

    public static function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        [$sql, $params] = self::buildListQuery($filters);
        $sql .= " ORDER BY c.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        return Database::all($sql, $params);
    }

    public static function count(array $filters = []): int
    {
        [$sql, $params] = self::buildListQuery($filters, true);
        return (int) Database::scalar($sql, $params);
    }

    private static function buildListQuery(array $f, bool $count = false): array
    {
        $select = $count
            ? "SELECT COUNT(*)"
            : "SELECT c.*, p.name AS product_name, p.sku AS product_sku, p.image_url, p.product_url";
        $from   = " FROM codes c LEFT JOIN products p ON p.id = c.product_id";
        $where  = [];
        $params = [];

        if (!empty($f['product_id'])) {
            $where[] = 'c.product_id = :pid';
            $params[':pid'] = (int) $f['product_id'];
        }
        if (!empty($f['status'])) {
            $where[] = 'c.status = :st';
            $params[':st'] = $f['status'];
        }
        if (!empty($f['mode'])) {
            $where[] = 'c.mode = :mode';
            $params[':mode'] = $f['mode'];
        }
        if (!empty($f['batch_number'])) {
            $where[] = 'c.batch_number = :bn';
            $params[':bn'] = $f['batch_number'];
        }
        if (!empty($f['high_risk'])) {
            $where[] = 'c.risk_score >= :rs';
            $params[':rs'] = (int) $f['high_risk'];
        }
        if (!empty($f['search'])) {
            $where[] = '(c.code LIKE :s OR c.code_normalized LIKE :s OR c.batch_number LIKE :s OR p.name LIKE :s OR p.sku LIKE :s)';
            $params[':s'] = '%' . $f['search'] . '%';
        }

        $sql = $select . $from;
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        return [$sql, $params];
    }

    /**
     * Auto-generate codes for a product.
     * @param array $opts product_id, batch_number, expiry_date, mode, length, quantity, recommended_product_ids
     */
    public static function generate(array $opts): array
    {
        $productId = (int) ($opts['product_id'] ?? 0);
        $product   = ProductService::find($productId);
        if (!$product) throw new \InvalidArgumentException('Invalid product.');

        $batch    = trim((string) ($opts['batch_number'] ?? ''));
        $expiry   = self::cleanDate($opts['expiry_date'] ?? null);
        $mode     = in_array($opts['mode'] ?? 'unique', ['unique', 'universal'], true) ? $opts['mode'] : 'unique';
        $length   = (int) ($opts['length'] ?? 12);
        $quantity = max(1, min(10000, (int) ($opts['quantity'] ?? 1)));
        $recommended = trim((string) ($opts['recommended_product_ids'] ?? ''));

        if ($length < 8 || $length > 16) {
            throw new \InvalidArgumentException('Length must be 8-16 digits.');
        }
        if ($mode === 'universal' && $quantity > 1) {
            $quantity = 1;
        }

        $created = []; $errors = [];
        Database::beginTransaction();
        try {
            for ($i = 0; $i < $quantity; $i++) {
                $code = self::randomNumeric($length);
                $tries = 0;
                while (self::findByNormalized($code)) {
                    $code = self::randomNumeric($length);
                    if (++$tries > 50) throw new \RuntimeException('Cannot generate unique code (collision).');
                }
                $id = Database::insert('codes', [
                    'code'                    => $code,
                    'code_normalized'         => $code,
                    'product_id'              => $productId,
                    'batch_number'            => $batch ?: null,
                    'expiry_date'             => $expiry,
                    'mode'                    => $mode,
                    'status'                  => 'active',
                    'recommended_product_ids' => $recommended ?: null,
                    'created_by'              => Auth::user()['id'] ?? null,
                ]);
                $created[] = ['id' => $id, 'code' => $code];
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        AuditLog::record('codes_generate', 'code', null, [
            'product_id' => $productId,
            'count'      => count($created),
            'mode'       => $mode,
        ]);

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Manual paste import: array of code strings, common product/batch/expiry/mode.
     */
    public static function pasteImport(array $opts): array
    {
        $productId = (int) ($opts['product_id'] ?? 0);
        if (!ProductService::find($productId)) {
            throw new \InvalidArgumentException('Invalid product.');
        }
        $batch    = trim((string) ($opts['batch_number'] ?? '')) ?: null;
        $expiry   = self::cleanDate($opts['expiry_date'] ?? null);
        $mode     = in_array($opts['mode'] ?? 'unique', ['unique', 'universal'], true) ? $opts['mode'] : 'unique';
        $codesRaw = (string) ($opts['codes'] ?? '');

        // split on newline + comma, then normalize each
        $tokens = preg_split('/[,\n\r]+/', $codesRaw) ?: [];
        $created = []; $skipped = []; $errors = [];

        Database::beginTransaction();
        try {
            foreach ($tokens as $tok) {
                $tok = trim($tok);
                if ($tok === '') continue;
                $n = CodeNormalizer::normalize($tok);
                if (!$n['ok']) {
                    $errors[] = "Skipped '{$tok}': {$n['error']}";
                    continue;
                }
                if (self::findByNormalized($n['normalized'])) {
                    $skipped[] = $n['normalized'];
                    continue;
                }
                $id = Database::insert('codes', [
                    'code'            => $n['normalized'],
                    'code_normalized' => $n['normalized'],
                    'product_id'      => $productId,
                    'batch_number'    => $batch,
                    'expiry_date'     => $expiry,
                    'mode'            => $mode,
                    'status'          => 'active',
                    'created_by'      => Auth::user()['id'] ?? null,
                ]);
                $created[] = ['id' => $id, 'code' => $n['normalized']];
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        AuditLog::record('codes_paste_import', 'code', null, [
            'product_id' => $productId,
            'created'    => count($created),
            'skipped'    => count($skipped),
        ]);

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * CSV import. Required cols: serial_code. Optional: wp_product_id,product_id,product_sku,batch_number,expiry_date,mode,status,scan_count
     * If product_id is missing, try to map by wp_product_id, then product_sku, then default options.product_id.
     */
    public static function importCsv(string $path, array $defaults = []): array
    {
        $created = 0; $updated = 0; $skipped = 0; $errors = [];
        $defaultProductId = (int) ($defaults['product_id'] ?? 0);
        $defaultMode      = in_array($defaults['mode'] ?? 'universal', ['unique','universal'], true) ? $defaults['mode'] : 'universal';
        $defaultExpiry    = self::cleanDate($defaults['expiry_date'] ?? null);

        Database::beginTransaction();
        try {
            foreach (CSV::read($path) as $rowNum => $row) {
                $serial = trim((string) ($row['serial_code'] ?? $row['code'] ?? ''));
                if ($serial === '') {
                    $errors[] = "Row {$rowNum}: missing serial_code";
                    $skipped++;
                    continue;
                }
                $n = CodeNormalizer::normalize($serial);
                if (!$n['ok']) {
                    $errors[] = "Row {$rowNum}: invalid code '{$serial}' ({$n['error']})";
                    $skipped++;
                    continue;
                }

                // resolve product
                $pid = 0;
                if (!empty($row['product_id'])) {
                    $pid = (int) $row['product_id'];
                } elseif (!empty($row['wp_product_id'])) {
                    $p = ProductService::findByWpId((int) $row['wp_product_id']);
                    if ($p) $pid = (int) $p['id'];
                } elseif (!empty($row['product_sku'])) {
                    $p = ProductService::findBySku((string) $row['product_sku']);
                    if ($p) $pid = (int) $p['id'];
                }
                if (!$pid && $defaultProductId) {
                    $pid = $defaultProductId;
                }
                if (!$pid || !ProductService::find($pid)) {
                    $errors[] = "Row {$rowNum}: cannot resolve product for code '{$n['normalized']}'";
                    $skipped++;
                    continue;
                }

                $payload = [
                    'product_id'      => $pid,
                    'batch_number'    => isset($row['batch_number']) ? (trim((string)$row['batch_number']) ?: null) : null,
                    'expiry_date'     => isset($row['expiry_date']) && $row['expiry_date'] !== '' ? self::cleanDate($row['expiry_date']) : $defaultExpiry,
                    'mode'            => isset($row['mode']) && in_array($row['mode'], ['unique','universal'], true) ? $row['mode'] : $defaultMode,
                    'status'          => isset($row['status']) && in_array($row['status'], ['active','inactive','quarantined'], true) ? $row['status'] : 'active',
                    'scan_count'      => isset($row['scan_count']) ? (int) $row['scan_count'] : 0,
                ];

                $existing = self::findByNormalized($n['normalized']);
                if ($existing) {
                    $payload['updated_at'] = date('Y-m-d H:i:s');
                    Database::update('codes', $payload, 'id', (int) $existing['id']);
                    $updated++;
                } else {
                    $payload['code']            = $n['normalized'];
                    $payload['code_normalized'] = $n['normalized'];
                    $payload['created_by']      = Auth::user()['id'] ?? null;
                    Database::insert('codes', $payload);
                    $created++;
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        AuditLog::record('codes_csv_import', 'code', null, [
            'created' => $created, 'updated' => $updated, 'skipped' => $skipped,
        ]);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['product_id', 'batch_number', 'expiry_date', 'mode', 'status', 'recommended_product_ids', 'notes'];
        $row = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if (is_string($v)) $v = trim($v);
                if ($v === '') $v = null;
                $row[$k] = $v;
            }
        }
        if (isset($row['expiry_date'])) $row['expiry_date'] = self::cleanDate($row['expiry_date']);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $rc = Database::update('codes', $row, 'id', $id);
        AuditLog::record('code_update', 'code', $id, $row);
        return $rc > 0;
    }

    public static function delete(int $id): bool
    {
        $rc = Database::exec("DELETE FROM codes WHERE id = :id", [':id' => $id])->rowCount();
        AuditLog::record('code_delete', 'code', $id);
        return $rc > 0;
    }

    public static function bulkUpdateStatus(array $ids, string $status): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) return 0;
        if (!in_array($status, ['active', 'inactive', 'quarantined'], true)) return 0;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("UPDATE codes SET status = ?, updated_at = NOW() WHERE id IN ({$place})");
        $stmt->execute(array_merge([$status], $ids));
        AuditLog::record('codes_bulk_status', 'code', null, ['ids' => $ids, 'status' => $status]);
        return $stmt->rowCount();
    }

    public static function bulkDelete(array $ids): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) return 0;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("DELETE FROM codes WHERE id IN ({$place})");
        $stmt->execute($ids);
        AuditLog::record('codes_bulk_delete', 'code', null, ['ids' => $ids]);
        return $stmt->rowCount();
    }

    private static function randomNumeric(int $length): string
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            // First digit: 1-9 to avoid leading zero (but DB still preserves zeros if pasted)
            if ($i === 0) {
                $digits .= (string) random_int(1, 9);
            } else {
                $digits .= (string) random_int(0, 9);
            }
        }
        return $digits;
    }

    private static function cleanDate($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (!is_string($v)) $v = (string) $v;
        $v = trim($v);
        // accept yyyy-mm-dd, dd-mm-yyyy, dd/mm/yyyy
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $ts = strtotime($v);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }
}
