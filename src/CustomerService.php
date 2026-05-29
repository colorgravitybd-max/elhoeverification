<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Customer registrations + VIP exports.
 */
final class CustomerService
{
    public static function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') return null;
        return Database::one("SELECT * FROM customers WHERE email = :e LIMIT 1", [':e' => $email]);
    }

    /**
     * Create customer record + bind to code (unique mode first scan).
     * @return int customer_id
     */
    public static function register(array $data): int
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = self::normalizePhone((string) ($data['phone'] ?? ''));
        $first = trim((string) ($data['first_name'] ?? ''));
        $last  = trim((string) ($data['last_name'] ?? ''));
        $city  = trim((string) ($data['city'] ?? ''));
        $country = strtoupper(substr(trim((string) ($data['country'] ?? '')), 0, 2)) ?: null;
        $consent = !empty($data['consent_marketing']) ? 1 : 0;

        $existing = $email !== '' ? self::findByEmail($email) : null;
        if ($existing) {
            // Update if needed but don't overwrite filled with empty
            $update = [];
            if ($first && !$existing['first_name']) $update['first_name'] = $first;
            if ($last  && !$existing['last_name'])  $update['last_name']  = $last;
            if ($phone && !$existing['phone'])      $update['phone']      = $phone;
            if ($phone && !$existing['phone_hash']) $update['phone_hash'] = hash('sha256', $phone);
            if ($city  && !$existing['city'])       $update['city']       = $city;
            if ($country && !$existing['country'])  $update['country']    = $country;
            if ($consent && !$existing['consent_marketing']) $update['consent_marketing'] = 1;
            if (!empty($update)) {
                Database::update('customers', $update, 'id', (int) $existing['id']);
            }
            return (int) $existing['id'];
        }

        return Database::insert('customers', [
            'email'             => $email ?: null,
            'email_hash'        => $email ? hash('sha256', $email) : null,
            'phone'             => $phone ?: null,
            'phone_hash'        => $phone ? hash('sha256', $phone) : null,
            'first_name'        => $first ?: null,
            'last_name'         => $last ?: null,
            'city'              => $city ?: null,
            'country'           => $country,
            'consent_marketing' => $consent,
            'first_product_id'  => $data['first_product_id'] ?? null,
            'first_code_id'     => $data['first_code_id'] ?? null,
            'registered_ip'     => $data['registered_ip'] ?? null,
        ]);
    }

    public static function list(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        [$sql, $params] = self::buildQuery($filters);
        $sql .= " ORDER BY c.registered_at DESC LIMIT {$limit} OFFSET {$offset}";
        return Database::all($sql, $params);
    }

    public static function count(array $filters = []): int
    {
        [$sql, $params] = self::buildQuery($filters, true);
        return (int) Database::scalar($sql, $params);
    }

    private static function buildQuery(array $f, bool $count = false): array
    {
        $select = $count
            ? "SELECT COUNT(*)"
            : "SELECT c.*, p.name AS first_product_name";
        $from = " FROM customers c LEFT JOIN products p ON p.id = c.first_product_id";
        $where = []; $params = [];

        if (!empty($f['product_id'])) {
            $where[] = 'c.first_product_id = :pid';
            $params[':pid'] = (int) $f['product_id'];
        }
        if (!empty($f['from'])) {
            $where[] = 'c.registered_at >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $where[] = 'c.registered_at <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['search'])) {
            $where[] = '(c.email LIKE :s OR c.first_name LIKE :s OR c.last_name LIKE :s OR c.phone LIKE :s)';
            $params[':s'] = '%' . $f['search'] . '%';
        }

        $sql = $select . $from;
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        return [$sql, $params];
    }

    /**
     * @param string $format meta | google | full
     */
    public static function streamExport(string $format, array $filters): void
    {
        $headers = match ($format) {
            'meta'   => ['fn', 'ln', 'email'],
            'google' => ['Email', 'First Name', 'Last Name'],
            default  => [
                'id', 'email', 'phone', 'first_name', 'last_name', 'city', 'country',
                'consent_marketing', 'first_product_name', 'registered_at',
            ],
        };

        // Stream rows
        $stream = function () use ($filters, $format) {
            $offset = 0;
            $limit  = 1000;
            while (true) {
                $rows = self::list($filters, $limit, $offset);
                if (!$rows) break;
                foreach ($rows as $r) {
                    yield self::formatExportRow($r, $format);
                }
                if (count($rows) < $limit) break;
                $offset += $limit;
            }
        };

        $filename = sprintf('elhoe-customers-%s-%s.csv', $format, date('Ymd-His'));
        CSV::downloadStream($filename, $headers, $stream());
    }

    private static function formatExportRow(array $r, string $format): array
    {
        $first = strtolower(trim((string) ($r['first_name'] ?? '')));
        $last  = strtolower(trim((string) ($r['last_name']  ?? '')));
        $email = strtolower(trim((string) ($r['email']      ?? '')));
        return match ($format) {
            'meta'   => ['fn' => $first, 'ln' => $last, 'email' => $email],
            'google' => ['Email' => $email, 'First Name' => $r['first_name'] ?? '', 'Last Name' => $r['last_name'] ?? ''],
            default  => [
                'id' => $r['id'] ?? '',
                'email' => $r['email'] ?? '',
                'phone' => $r['phone'] ?? '',
                'first_name' => $r['first_name'] ?? '',
                'last_name'  => $r['last_name'] ?? '',
                'city' => $r['city'] ?? '',
                'country' => $r['country'] ?? '',
                'consent_marketing' => $r['consent_marketing'] ?? 0,
                'first_product_name' => $r['first_product_name'] ?? '',
                'registered_at' => $r['registered_at'] ?? '',
            ],
        };
    }

    private static function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[^\d+]/', '', $phone) ?? '';
        return $p;
    }
}
