<?php
/**
 * IntuiFy Admin — Audit log
 * Writes one row per sensitive admin action to the `audit_logs` table.
 * Never blocks the request: if Supabase is down (or the table is missing) it only logs.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/client-ip.php';

function auditLog(string $action, string $resourceType = '', string $resourceId = '', array $metadata = []): void
{
    try {
        require_once __DIR__ . '/supabase.php';
        getSupabase()->insert('audit_logs', [
            'actor'         => (string) ($_SESSION['admin_username'] ?? 'anonymous'),
            'action'        => $action,
            'resource_type' => $resourceType !== '' ? $resourceType : null,
            'resource_id'   => $resourceId !== '' ? $resourceId : null,
            'metadata'      => $metadata ?: new \stdClass(),
            'ip'            => clientIp(),
        ]);
    } catch (\Throwable $e) {
        error_log('ADMIN [AUDIT] Failed to write "' . $action . '": ' . $e->getMessage());
    }
}
