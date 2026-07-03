<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * Matriz de Permissões (OPS-PRD-001 §3.5) - camada de leitura/escrita da ACL.
 * Responsável apenas por Base de Dados (11.8).
 */
final class PermissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_permission WHERE deleted_at IS NULL ORDER BY code ASC
        ')->fetchAll();
    }

    /** @return int[] ids de permissão atribuídos ao perfil (ativos). */
    public function permissionIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT permission_id FROM tb_role_permission
            WHERE role_id = :role_id AND deleted_at IS NULL
        ');
        $stmt->execute(['role_id' => $roleId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }

    /**
     * Substitui a lista de permissões de um perfil.
     *
     * uq_role_permission é único por (role_id, permission_id) independentemente
     * de deleted_at, por isso "ressuscitamos" a linha soft-deleted em vez de
     * inserir de novo (mesmo padrão de tb_user_batch).
     *
     * @param int[] $desiredPermissionIds
     */
    public function syncForRole(int $roleId, array $desiredPermissionIds, int $actingUserId): void
    {
        $desired = array_unique(array_map('intval', $desiredPermissionIds));
        $current = $this->permissionIdsForRole($roleId);

        $toRemove = array_diff($current, $desired);
        $toAdd = array_diff($desired, $current);

        if ($toRemove !== []) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $stmt = $this->pdo->prepare("
                UPDATE tb_role_permission SET deleted_at = NOW(), deleted_by = ?
                WHERE role_id = ? AND deleted_at IS NULL AND permission_id IN ({$placeholders})
            ");
            $stmt->execute([$actingUserId, $roleId, ...array_values($toRemove)]);
        }

        $reviveStmt = $this->pdo->prepare('
            UPDATE tb_role_permission SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :acting_user
            WHERE role_id = :role_id AND permission_id = :permission_id AND deleted_at IS NOT NULL
        ');
        $insertStmt = $this->pdo->prepare('
            INSERT INTO tb_role_permission (uuid, role_id, permission_id, created_at, created_by)
            VALUES (UUID(), :role_id, :permission_id, NOW(), :created_by)
        ');

        foreach ($toAdd as $permissionId) {
            $reviveStmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId, 'acting_user' => $actingUserId]);

            if ($reviveStmt->rowCount() === 0) {
                $insertStmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_by' => $actingUserId]);
            }
        }
    }
}
