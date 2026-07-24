<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

final class PriorityRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_priority WHERE active = 1 AND deleted_at IS NULL ORDER BY sort_order ASC
        ')->fetchAll();
    }

    /**
     * RF-0046 - Configurar Prioridades (inclui inativas, para gestão).
     */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_priority WHERE deleted_at IS NULL ORDER BY sort_order ASC
        ')->fetchAll();
    }

    /**
     * $nextContactAutoHours: horas para agendar sozinho a Nova Data de
     * Contacto. null = manual (o operador escolhe a data no calendário).
     */
    public function create(string $code, string $name, string $color, int $sortOrder, ?int $defaultSlaMinutes, ?int $nextContactAutoHours, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_priority (uuid, code, name, color, sort_order, default_sla_minutes, next_contact_auto_hours, active, created_at, created_by)
            VALUES (UUID(), :code, :name, :color, :sort_order, :sla, :auto_hours, 1, NOW(), :user_id)
        ');
        $stmt->execute([
            'code' => $code, 'name' => $name, 'color' => $color,
            'sort_order' => $sortOrder, 'sla' => $defaultSlaMinutes,
            'auto_hours' => $nextContactAutoHours, 'user_id' => $userId,
        ]);
    }

    public function update(int $id, string $name, string $color, int $sortOrder, ?int $defaultSlaMinutes, ?int $nextContactAutoHours, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_priority
            SET name = :name, color = :color, sort_order = :sort_order, default_sla_minutes = :sla,
                next_contact_auto_hours = :auto_hours,
                updated_at = NOW(), updated_by = :user_id
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id, 'name' => $name, 'color' => $color,
            'sort_order' => $sortOrder, 'sla' => $defaultSlaMinutes,
            'auto_hours' => $nextContactAutoHours, 'user_id' => $userId,
        ]);
    }

    public function toggleActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_priority SET active = :active, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'user_id' => $userId]);
    }
}
