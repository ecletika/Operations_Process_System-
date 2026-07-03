<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0034 - Criar Departamento.
 */
final class DepartmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT d.*, b.name AS branch_name
            FROM tb_department d
            JOIN tb_branch b ON b.id = d.branch_id
            WHERE d.deleted_at IS NULL
            ORDER BY b.name ASC, d.name ASC
        ')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_department WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $department = $stmt->fetch();

        return $department ?: null;
    }

    public function create(int $branchId, string $code, string $name, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_department (uuid, branch_id, code, name, active, created_at, created_by)
            VALUES (UUID(), :branch_id, :code, :name, 1, NOW(), :user_id)
        ');
        $stmt->execute(['branch_id' => $branchId, 'code' => $code, 'name' => $name, 'user_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_department SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
