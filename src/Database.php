<?php
declare(strict_types=1);

namespace Elhoe;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Singleton PDO wrapper.
 * Uses utf8mb4, throws on errors, prepared statements only.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) env('DB_HOST', 'localhost');
        $port    = (string) env('DB_PORT', '3306');
        $name    = (string) env('DB_NAME', '');
        $user    = (string) env('DB_USER', '');
        $pass    = (string) env('DB_PASS', '');
        $charset = (string) env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Emulated prepares: allows reusing the same named placeholder
                // multiple times in a single query (e.g. "WHERE a=:s OR b=:s").
                // Still safe from SQL injection because PDO::quote() handles
                // escaping with the connection charset (set below to utf8mb4).
                PDO::ATTR_EMULATE_PREPARES   => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci, sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            // Don't leak credentials. Log full, show generic.
            Logger::error('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            $msg = env('APP_DEBUG', false)
                ? 'DB error: ' . $e->getMessage()
                : 'Service temporarily unavailable. Please try again in a moment.';
            die($msg);
        }
        return self::$pdo;
    }

    /** Run a query and return all rows. */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a query and return one row, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Run a query and return scalar value of first column of first row. */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /** Execute a write query. Returns the PDOStatement. */
    public static function exec(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        self::pdo()->commit();
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /** Convenience: simple insert. Returns lastInsertId. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => '`' . $c . '`', $cols)),
            implode(', ', $placeholders)
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }
        self::exec($sql, $params);
        return self::lastInsertId();
    }

    /** Convenience: simple update by single-column condition. */
    public static function update(string $table, array $data, string $whereCol, $whereVal): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $k => $v) {
            $sets[] = '`' . $k . '` = :' . $k;
            $params[':' . $k] = $v;
        }
        $params[':__where'] = $whereVal;
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :__where',
            $table,
            implode(', ', $sets),
            $whereCol
        );
        return self::exec($sql, $params)->rowCount();
    }
}
