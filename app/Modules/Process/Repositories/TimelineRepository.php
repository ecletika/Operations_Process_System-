<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 5.10 - construída automaticamente a partir dos Eventos,
 * nunca editada manualmente. RF-0019: mais recente primeiro.
 */
final class TimelineRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $processId, int $eventId, string $title, ?string $description, string $icon, string $color): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_timeline (uuid, process_id, event_id, title, description, icon, color, visible_to_operator, created_at)
            VALUES (UUID(), :process_id, :event_id, :title, :description, :icon, :color, 1, NOW())
        ');
        $stmt->execute([
            'process_id' => $processId,
            'event_id' => $eventId,
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'color' => $color,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Timeline Global™ - últimos acontecimentos de TODOS os processos,
     * para acompanhar a operação inteira num só sítio.
     */
    public function listGlobal(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.title, t.description, t.icon, t.color, t.created_at,
                   p.id AS process_id, p.process_number,
                   c.name AS customer_name,
                   u.first_name, u.last_name
            FROM tb_timeline t
            JOIN tb_process p ON p.id = t.process_id AND p.deleted_at IS NULL
            JOIN tb_customer c ON c.id = p.customer_id
            LEFT JOIN tb_event e ON e.id = t.event_id
            LEFT JOIN tb_user u ON u.id = e.user_id
            WHERE t.deleted_at IS NULL
            ORDER BY t.created_at DESC, t.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listByProcess(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_timeline
            WHERE process_id = :process_id AND deleted_at IS NULL
            ORDER BY created_at DESC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }

    /**
     * Operations Replay™ (OPS-UI-001 §24) - ordem cronológica (mais antigo
     * primeiro) com o autor de cada passo, para "reproduzir" a história do
     * processo do início ao fim.
     */
    public function listForReplay(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.id, t.title, t.description, t.icon, t.color, t.created_at,
                   u.first_name, u.last_name
            FROM tb_timeline t
            LEFT JOIN tb_event e ON e.id = t.event_id
            LEFT JOIN tb_user u ON u.id = e.user_id
            WHERE t.process_id = :process_id AND t.deleted_at IS NULL
            ORDER BY t.created_at ASC, t.id ASC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }
}
