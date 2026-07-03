<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0032 - Criar Empresa.
 */
final class CompanyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_company WHERE deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    public function create(string $code, string $name, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_company (uuid, code, name, active, created_at, created_by)
            VALUES (UUID(), :code, :name, 1, NOW(), :user_id)
        ');
        $stmt->execute(['code' => $code, 'name' => $name, 'user_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function hasActiveBranches(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tb_branch WHERE company_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * RN-0048 - nunca DELETE físico; soft delete via deleted_at.
     */
    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_company SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
