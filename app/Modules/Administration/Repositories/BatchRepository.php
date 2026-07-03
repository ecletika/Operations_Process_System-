<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0035 - Criar Lote.
 */
final class BatchRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT bt.*, d.name AS department_name, br.name AS branch_name
            FROM tb_batch bt
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            WHERE bt.deleted_at IS NULL
            ORDER BY br.name ASC, d.name ASC
        ')->fetchAll();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_batch WHERE active = 1 AND deleted_at IS NULL ORDER BY code ASC
        ')->fetchAll();
    }

    public function create(int $departmentId, string $code, string $description, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_batch (uuid, department_id, code, description, active, created_at, created_by)
            VALUES (UUID(), :department_id, :code, :description, 1, NOW(), :user_id)
        ');
        $stmt->execute(['department_id' => $departmentId, 'code' => $code, 'description' => $description, 'user_id' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findActiveByDepartment(int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_batch WHERE department_id = :department_id AND deleted_at IS NULL ORDER BY id ASC LIMIT 1
        ');
        $stmt->execute(['department_id' => $departmentId]);
        $batch = $stmt->fetch();

        return $batch ?: null;
    }

    /**
     * Simplificação pedida pelo utilizador: já não se gere "Lote" à mão —
     * cada Departamento tem sempre um Lote próprio, criado automaticamente
     * na primeira vez que é preciso (ao criar o Departamento, ou aqui como
     * rede de segurança para departamentos antigos que ainda não tinham).
     * A arquitetura por lote (Fila Inteligente™, Dashboard, Relatórios)
     * continua exatamente igual "por dentro".
     */
    public function ensureForDepartment(int $departmentId, string $departmentName, int $userId): int
    {
        $existing = $this->findActiveByDepartment($departmentId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->create($departmentId, 'AUTO-' . $departmentId, $departmentName . ' (lote automático)', $userId);
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT
                (SELECT COUNT(*) FROM tb_process WHERE batch_id = :id1 AND deleted_at IS NULL) +
                (SELECT COUNT(*) FROM tb_user_batch WHERE batch_id = :id2 AND deleted_at IS NULL)
        ');
        $stmt->execute(['id1' => $id, 'id2' => $id]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_batch SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
