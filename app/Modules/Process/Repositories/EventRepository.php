<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 5.9 - "Se não gera um Evento, então não aconteceu."
 * RN-0026 / RN-0027 - nenhum evento pode ser alterado ou eliminado.
 */
final class EventRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $processId, string $eventType, string $title, ?string $description, ?int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_event (uuid, process_id, event_type, title, description, user_id, created_at)
            VALUES (UUID(), :process_id, :event_type, :title, :description, :user_id, NOW())
        ');
        $stmt->execute([
            'process_id' => $processId,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'user_id' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByProcess(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT e.*, u.first_name, u.last_name
            FROM tb_event e
            LEFT JOIN tb_user u ON u.id = e.user_id
            WHERE e.process_id = :process_id AND e.deleted_at IS NULL
            ORDER BY e.created_at DESC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }
}
