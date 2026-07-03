<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0033 - Criar Filial.
 */
final class BranchRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT b.*, c.name AS company_name
            FROM tb_branch b
            JOIN tb_company c ON c.id = b.company_id
            WHERE b.deleted_at IS NULL
            ORDER BY c.name ASC, b.name ASC
        ')->fetchAll();
    }

    public function create(int $companyId, string $code, string $name, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_branch (uuid, company_id, code, name, active, created_at, created_by)
            VALUES (UUID(), :company_id, :code, :name, 1, NOW(), :user_id)
        ');
        $stmt->execute(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'user_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function hasActiveDepartments(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tb_department WHERE branch_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_branch SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
