<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Tiny file logger. Daily-rotated files in storage/logs/.
 * Honours LOG_LEVEL from .env.
 */
final class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];

    public static function debug(string $msg, array $ctx = []): void   { self::write('debug',   $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void    { self::write('info',    $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::write('error',   $msg, $ctx); }

    private static function write(string $level, string $msg, array $ctx): void
    {
        $minLevel = strtolower((string) env('LOG_LEVEL', 'info'));
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$minLevel] ?? 20)) {
            return;
        }
        $dir  = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/app-' . date('Y-m-d') . '.log';

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $msg,
            empty($ctx) ? '' : ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
