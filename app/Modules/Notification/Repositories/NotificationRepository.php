<?php

declare(strict_types=1);

namespace App\Modules\Notification\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 TABELA 18 (tb_notification) / RF-0038 a RF-0040.
 */
final class NotificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $userId, string $title, string $message, string $severity = 'INFO', ?string $link = null): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_notification (uuid, user_id, title, message, severity, link, created_at)
            VALUES (UUID(), :user_id, :title, :message, :severity, :link, NOW())
        ');
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'link' => $link,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listForUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_notification
            WHERE user_id = :user_id AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM tb_notification
            WHERE user_id = :user_id AND deleted_at IS NULL AND read_at IS NULL
        ');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_notification SET read_at = NOW(), updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND read_at IS NULL
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_notification SET read_at = NOW(), updated_at = NOW()
            WHERE user_id = :user_id AND read_at IS NULL
        ');
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Evita repetir o mesmo alerta várias vezes seguidas (usado pelos jobs
     * agendados de RN-0056/RF-0039).
     */
    public function existsRecent(int $userId, string $title, int $withinMinutes): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM tb_notification
            WHERE user_id = :user_id AND title = :title AND deleted_at IS NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('title', $title, PDO::PARAM_STR);
        $stmt->bindValue('minutes', $withinMinutes, PDO::PARAM_INT);
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
