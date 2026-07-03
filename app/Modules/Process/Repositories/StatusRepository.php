<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0045 - Configurar Estados. Os códigos usados na máquina de estados
 * (ProcessService::TRANSITIONS) são fixos; esta tela permite ajustar apenas
 * nome, ordem e ativação de estados existentes.
 */
final class StatusRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_status WHERE deleted_at IS NULL ORDER BY sort_order ASC
        ')->fetchAll();
    }

    public function update(int $id, string $name, int $sortOrder, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_status SET name = :name, sort_order = :sort_order, updated_at = NOW(), updated_by = :user_id
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'name' => $name, 'sort_order' => $sortOrder, 'user_id' => $userId]);
    }

    public function toggleActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_status SET active = :active, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'user_id' => $userId]);
    }
}
