<?php

declare(strict_types=1);

namespace App\Traits;

use App\Core\Database;
use App\Core\Session;

/**
 * OPS-PRD-001 capítulo 4.20 / 5.11 - "Se não gera um Evento, não aconteceu."
 * Toda ação relevante grava uma linha imutável em tb_audit.
 */
trait AuditTrait
{
    protected function logAudit(
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            INSERT INTO tb_audit
                (uuid, table_name, record_id, action, old_values, new_values,
                 session_id, ip_address, user_agent, request_method, request_url, user_id, created_at)
            VALUES
                (UUID(), :table_name, :record_id, :action, :old_values, :new_values,
                 :session_id, :ip_address, :user_agent, :request_method, :request_url, :user_id, NOW())
        ');

        $stmt->execute([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => $action,
            'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'session_id' => session_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'request_url' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => Session::get('user_id'),
        ]);
    }
}
