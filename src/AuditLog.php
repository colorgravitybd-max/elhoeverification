<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Records admin actions for accountability.
 */
final class AuditLog
{
    public static function record(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        try {
            $admin = Auth::user();
            Database::insert('audit_log', [
                'admin_id'       => $admin['id']       ?? null,
                'admin_username' => $admin['username'] ?? null,
                'action'         => $action,
                'entity_type'    => $entityType,
                'entity_id'      => $entityId,
                'details'        => empty($details) ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address'     => Auth::ip(),
                'user_agent'     => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]);
        } catch (\Throwable $e) {
            Logger::error('AuditLog write failed: ' . $e->getMessage());
        }
    }
}
