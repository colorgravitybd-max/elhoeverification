<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Product CRUD + CSV import/export.
 */
final class ProductService
{
    public static function list(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params[':category'] = $filters['category'];
        }
        if (!empty($filters['routine_group'])) {
            $where[] = 'routine_group = :routine_group';
            $params[':routine_group'] = $filters['routine_group'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :s OR sku LIKE :s OR slug LIKE :s)';
            $params[':s'] = '%' . $filters['search'] . '%';
        }
        $sql = "SELECT * FROM products WHERE " . implode(' AND ', $where) .
               " ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}";
        return Database::all($sql, $params);
    }

    public static function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :s OR sku LIKE :s)';
            $params[':s'] = '%' . $filters['search'] . '%';
        }
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM products WHERE " . implode(' AND ', $where),
            $params
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one("SELECT * FROM products WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    public static function findByWpId(int $wpId): ?array
    {
        return Database::one("SELECT * FROM products WHERE wp_product_id = :wp LIMIT 1", [':wp' => $wpId]);
    }

    public static function findBySku(string $sku): ?array
    {
        if ($sku === '') return null;
        return Database::one("SELECT * FROM products WHERE sku = :sku LIMIT 1", [':sku' => $sku]);
    }

    public static function create(array $data): int
    {
        $row = self::sanitize($data);
        $row['slug'] = $row['slug'] ?: self::makeSlug((string) $row['name']);
        $row['created_at'] = date('Y-m-d H:i:s');
        $id = Database::insert('products', $row);
        AuditLog::record('product_create', 'product', $id, ['name' => $row['name']]);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $row = self::sanitize($data);
        unset($row['created_at']);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $rc = Database::update('products', $row, 'id', $id);
        AuditLog::record('product_update', 'product', $id);
        return $rc > 0;
    }

    public static function delete(int $id): bool
    {
        $codeCount = (int) Database::scalar("SELECT COUNT(*) FROM codes WHERE product_id = :p", [':p' => $id]);
        if ($codeCount > 0) {
            throw new \RuntimeException("Cannot delete product: {$codeCount} codes still reference it. Delete codes first or set product to inactive.");
        }
        $rc = Database::exec("DELETE FROM products WHERE id = :id", [':id' => $id])->rowCount();
        AuditLog::record('product_delete', 'product', $id);
        return $rc > 0;
    }

    public static function categories(): array
    {
        $rows = Database::all("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category");
        return array_column($rows, 'category');
    }

    public static function routineGroups(): array
    {
        $rows = Database::all("SELECT DISTINCT routine_group FROM products WHERE routine_group IS NOT NULL AND routine_group <> '' ORDER BY routine_group");
        return array_column($rows, 'routine_group');
    }

    /**
     * Import products from a CSV file. Returns counters.
     * Required columns: name. Optional: wp_product_id,sku,slug,category,routine_group,image_url,product_url,description,status
     * Duplicates: by sku if present, else by wp_product_id, else by slug, else by name.
     */
    public static function importCsv(string $path): array
    {
        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        Database::beginTransaction();
        try {
            foreach (CSV::read($path) as $rowNum => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $errors[] = "Row {$rowNum}: missing 'name'";
                    $skipped++;
                    continue;
                }
                $data = [
                    'wp_product_id' => isset($row['wp_product_id']) && $row['wp_product_id'] !== '' ? (int) $row['wp_product_id'] : null,
                    'sku'           => $row['sku'] ?? null,
                    'name'          => $name,
                    'slug'          => $row['slug'] ?? null,
                    'category'      => $row['category'] ?? null,
                    'routine_group' => $row['routine_group'] ?? null,
                    'image_url'     => $row['image_url'] ?? null,
                    'product_url'   => $row['product_url'] ?? null,
                    'description'   => $row['description'] ?? null,
                    'status'        => $row['status'] ?? 'active',
                ];

                // Find existing
                $existing = null;
                if (!empty($data['sku'])) {
                    $existing = self::findBySku((string) $data['sku']);
                }
                if (!$existing && !empty($data['wp_product_id'])) {
                    $existing = self::findByWpId((int) $data['wp_product_id']);
                }
                if (!$existing && !empty($data['slug'])) {
                    $existing = Database::one("SELECT * FROM products WHERE slug = :s LIMIT 1", [':s' => $data['slug']]);
                }

                if ($existing) {
                    self::update((int) $existing['id'], $data);
                    $updated++;
                } else {
                    self::create($data);
                    $created++;
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    private static function sanitize(array $in): array
    {
        $out = [];
        $allowed = ['wp_product_id', 'sku', 'name', 'slug', 'category', 'routine_group', 'image_url', 'product_url', 'description', 'status'];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $in)) {
                $v = $in[$k];
                if (is_string($v)) $v = trim($v);
                if ($v === '') $v = null;
                $out[$k] = $v;
            }
        }
        if (!empty($out['status']) && !in_array($out['status'], ['active', 'inactive'], true)) {
            $out['status'] = 'active';
        }
        if (!empty($out['wp_product_id'])) $out['wp_product_id'] = (int) $out['wp_product_id'];
        return $out;
    }

    private static function makeSlug(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') $s = 'product-' . substr(md5(uniqid('', true)), 0, 6);
        // Avoid collisions
        $base = $s; $i = 1;
        while (Database::one("SELECT id FROM products WHERE slug = :s LIMIT 1", [':s' => $s])) {
            $s = $base . '-' . (++$i);
        }
        return $s;
    }
}
