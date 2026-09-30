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

    /**
     * Substitui a lista de departamentos que podem ver o separador
     * "Imobilizados (todos)".
     *
     * Grava tudo de uma vez — os que não vierem na lista ficam sem acesso.
     * É o que faz o ecrã ser honesto: o que lá está é exatamente o que vale,
     * sem sobras de uma gravação anterior.
     *
     * @param int[] $departmentIds
     */
    public function syncImobilizadosViewAll(array $departmentIds, int $actingUserId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $departmentIds))));

        $this->pdo->prepare('
            UPDATE tb_department
            SET imobilizados_view_all = 0, updated_at = NOW(), updated_by = :acting
            WHERE imobilizados_view_all = 1 AND deleted_at IS NULL
        ')->execute(['acting' => $actingUserId]);

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE tb_department
            SET imobilizados_view_all = 1, updated_at = NOW(), updated_by = ?
            WHERE id IN ({$placeholders}) AND deleted_at IS NULL
        ");
        $stmt->execute([$actingUserId, ...$ids]);
    }

    /** @return int[] departamentos que podem ver o separador dos imobilizados */
    public function imobilizadosViewAllIds(): array
    {
        if (!Database::hasColumn('tb_department', 'imobilizados_view_all')) {
            return []; // migração 040 ainda não aplicada
        }

        $rows = $this->pdo->query('
            SELECT id FROM tb_department
            WHERE imobilizados_view_all = 1 AND deleted_at IS NULL
        ')->fetchAll();

        return array_map('intval', array_column($rows, 'id'));
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
