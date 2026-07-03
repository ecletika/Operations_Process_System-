<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database;
use PDO;

final class LoginLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function record(?int $userId, bool $success, string $ip, ?string $userAgent): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_login_log (uuid, user_id, login_at, ip_address, browser, success, created_at)
            VALUES (UUID(), :user_id, NOW(), :ip, :browser, :success, NOW())
        ');
        $stmt->execute([
            'user_id' => $userId,
            'ip' => $ip,
            'browser' => $userAgent,
            'success' => $success ? 1 : 0,
        ]);
    }

    /**
     * Carimba a saída na sessão aberta mais recente do utilizador, para que
     * "Sessões Ativas" reflita quem está mesmo online.
     */
    public function markLogout(int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_login_log
            SET logout_at = NOW(), updated_at = NOW()
            WHERE user_id = :user_id AND success = 1 AND logout_at IS NULL AND deleted_at IS NULL
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Sessões Ativas: login com sucesso, sem logout registado, dentro da
     * janela de tempo de sessão (só a sessão aberta mais recente por utilizador).
     */
    public function activeSessions(int $timeoutMinutes): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id AS user_id, u.username, u.first_name, u.last_name,
                   ll.ip_address, ll.browser, ll.login_at,
                   TIMESTAMPDIFF(MINUTE, ll.login_at, NOW()) AS minutos_ativo
            FROM tb_login_log ll
            JOIN tb_user u ON u.id = ll.user_id
            WHERE ll.id IN (
                SELECT MAX(id) FROM tb_login_log
                WHERE success = 1 AND logout_at IS NULL AND deleted_at IS NULL AND user_id IS NOT NULL
                GROUP BY user_id
            )
            AND ll.login_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
            ORDER BY ll.login_at DESC
        ');
        $stmt->bindValue('minutes', $timeoutMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Tentativas de Login (Histórico de Acessos). $success: null = todas,
     * true = só bem-sucedidas, false = só falhadas.
     */
    public function recentAttempts(?bool $success, int $limit = 100): array
    {
        $where = 'll.deleted_at IS NULL';
        if ($success !== null) {
            $where .= ' AND ll.success = :success';
        }

        $stmt = $this->pdo->prepare("
            SELECT ll.id, ll.login_at, ll.logout_at, ll.ip_address, ll.browser, ll.success,
                   u.username, u.first_name, u.last_name
            FROM tb_login_log ll
            LEFT JOIN tb_user u ON u.id = ll.user_id
            WHERE {$where}
            ORDER BY ll.login_at DESC
            LIMIT :limit
        ");
        if ($success !== null) {
            $stmt->bindValue('success', $success ? 1 : 0, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
