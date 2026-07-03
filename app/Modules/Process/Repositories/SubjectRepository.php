<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

final class SubjectRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_subject WHERE active = 1 AND deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    /**
     * RF-0047 - Configurar Assuntos (inclui inativos, para gestão).
     */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_subject WHERE deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    public function create(string $code, string $name, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_subject (uuid, code, name, active, created_at, created_by)
            VALUES (UUID(), :code, :name, 1, NOW(), :user_id)
        ');
        $stmt->execute(['code' => $code, 'name' => $name, 'user_id' => $userId]);
    }

    public function update(int $id, string $name, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_subject SET name = :name, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'name' => $name, 'user_id' => $userId]);
    }

    public function toggleActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_subject SET active = :active, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'user_id' => $userId]);
    }
}
