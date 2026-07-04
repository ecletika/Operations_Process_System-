<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Auditoria global: regista automaticamente TODA a ação que muda estado
 * (qualquer POST/PUT/PATCH/DELETE em qualquer menu), para além das entradas
 * detalhadas do AuditTrait. Garante "tudo o que se faz fica registado".
 */
final class Audit
{
    /** Métodos que alteram estado e por isso são auditados. */
    private const AUDITED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public static function logRequest(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, self::AUDITED_METHODS, true)) {
            return;
        }

        $url = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        // Primeiro segmento do URL como "área" (customers, processes, admin...).
        $segment = explode('/', trim($url, '/'))[0] ?? 'root';
        $tableName = 'menu:' . ($segment !== '' ? $segment : 'root');

        // A auditoria nunca pode quebrar o pedido do utilizador.
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('
                INSERT INTO tb_audit
                    (uuid, table_name, record_id, action, session_id, ip_address,
                     user_agent, request_method, request_url, user_id, created_at)
                VALUES
                    (UUID(), :table_name, 0, :action, :session_id, :ip_address,
                     :user_agent, :request_method, :request_url, :user_id, NOW())
            ');
            $stmt->execute([
                'table_name' => $tableName,
                'action' => 'REQUEST',
                'session_id' => session_id() ?: null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $method,
                'request_url' => $url,
                'user_id' => Session::get('user_id'),
            ]);
        } catch (Throwable) {
            // silencioso de propósito
        }
    }
}
