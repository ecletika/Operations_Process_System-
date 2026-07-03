<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database;
use PDO;

/**
 * Responsável apenas por Base de Dados. Nunca possuirá regra de negócio
 * (OPS-PRD-001 capítulo 11.8).
 */
final class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.*, r.code AS role_code, r.name AS role_name
            FROM tb_user u
            JOIN tb_role r ON r.id = u.role_id
            WHERE u.username = :username AND u.deleted_at IS NULL
        ');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.*, r.code AS role_code, r.name AS role_name
            FROM tb_user u
            JOIN tb_role r ON r.id = u.role_id
            WHERE u.id = :id AND u.deleted_at IS NULL
        ');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Lote de trabalho do utilizador (3.7 - um utilizador pode ter vários
     * lotes). Usa o "Lote Padrão" escolhido explicitamente no seu perfil;
     * se não tiver nenhum definido (ou já não estiver mais atribuído a ele),
     * cai para o primeiro lote atribuído por omissão.
     */
    public function primaryBatchId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT ub.batch_id
            FROM tb_user u
            JOIN tb_user_batch ub ON ub.user_id = u.id AND ub.batch_id = u.default_batch_id AND ub.deleted_at IS NULL
            WHERE u.id = :user_id AND u.default_batch_id IS NOT NULL
        ');
        $stmt->execute(['user_id' => $userId]);
        $batchId = $stmt->fetchColumn();

        if ($batchId !== false) {
            return (int) $batchId;
        }

        $stmt = $this->pdo->prepare('
            SELECT batch_id FROM tb_user_batch
            WHERE user_id = :user_id AND deleted_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId]);
        $batchId = $stmt->fetchColumn();

        return $batchId !== false ? (int) $batchId : null;
    }

    public function setDefaultBatch(int $userId, ?int $batchId, int $actingUserId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user SET default_batch_id = :batch_id, updated_at = NOW(), updated_by = :updated_by WHERE id = :id
        ');
        $stmt->execute(['id' => $userId, 'batch_id' => $batchId, 'updated_by' => $actingUserId]);
    }

    /**
     * RF-0038 - usado para notificar responsáveis da operação (Supervisor/Admin)
     * quando algo relevante acontece num lote/empresa (novo processo, alerta, etc.).
     */
    public function findSupervisorsAndAdmins(int $companyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id FROM tb_user u
            JOIN tb_role r ON r.id = u.role_id
            WHERE u.company_id = :company_id AND u.deleted_at IS NULL AND u.active = 1
              AND r.code IN ('ROLE_SUPERVISOR', 'ROLE_ADMIN')
        ");
        $stmt->execute(['company_id' => $companyId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /**
     * RN-0057 - nº de processos ativos atribuídos a um operador.
     */
    public function activeProcessCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL AND p.assigned_to = :user_id AND s.code NOT IN ('SOLVED', 'CLOSED')
        ");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function permissionsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.code
            FROM tb_role_permission rp
            JOIN tb_permission p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id AND rp.deleted_at IS NULL
        ');
        $stmt->execute(['role_id' => $roleId]);

        return array_column($stmt->fetchAll(), 'code');
    }

    public function registerFailedAttempt(int $userId, int $maxAttempts, int $lockMinutes): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user
            SET failed_attempts = failed_attempts + 1,
                locked_until = CASE
                    WHEN failed_attempts + 1 >= :max_attempts THEN DATE_ADD(NOW(), INTERVAL :lock_minutes MINUTE)
                    ELSE locked_until
                END,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $userId, 'max_attempts' => $maxAttempts, 'lock_minutes' => $lockMinutes]);
    }

    public function resetFailedAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user
            SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $userId]);
    }

    /**
     * RF-0028 a RF-0031 - Gestão de Utilizadores (Administração).
     */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT u.*, r.name AS role_name, c.name AS company_name, b.name AS branch_name, d.name AS department_name
            FROM tb_user u
            JOIN tb_role r ON r.id = u.role_id
            JOIN tb_company c ON c.id = u.company_id
            JOIN tb_branch b ON b.id = u.branch_id
            JOIN tb_department d ON d.id = u.department_id
            WHERE u.deleted_at IS NULL
            ORDER BY b.name ASC, d.name ASC, u.first_name ASC, u.last_name ASC
        ')->fetchAll();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tb_user WHERE email = :email AND deleted_at IS NULL');
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();

        return $id !== false ? ['id' => (int) $id] : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_user
                (uuid, username, email, password, first_name, last_name, role_id, company_id, branch_id, department_id, view_all_batches, active, created_at, created_by)
            VALUES
                (UUID(), :username, :email, :password, :first_name, :last_name, :role_id, :company_id, :branch_id, :department_id, :view_all_batches, 1, NOW(), :created_by)
        ');
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user
            SET first_name = :first_name, last_name = :last_name, email = :email,
                role_id = :role_id, company_id = :company_id, branch_id = :branch_id, department_id = :department_id,
                view_all_batches = :view_all_batches,
                updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id
        ');
        $stmt->execute($data + ['id' => $id]);
    }

    public function setActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user SET active = :active, updated_at = NOW(), updated_by = :updated_by WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'updated_by' => $userId]);
    }

    /**
     * "Meu Perfil" - o próprio utilizador altera o seu nome/email (RF-0030
     * proíbe apagar, não impede autogestão dos próprios dados de perfil).
     */
    public function updateOwnProfile(int $id, string $firstName, string $lastName, string $email): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user
            SET first_name = :first_name, last_name = :last_name, email = :email,
                updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'updated_by' => $id,
        ]);
    }

    /** Altera apenas a password (já vem com hash calculado pelo Service). */
    public function updatePassword(int $id, string $hashedPassword): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_user
            SET password = :password, updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'password' => $hashedPassword, 'updated_by' => $id]);
    }

    /**
     * RF-0031 - substitui a associação a lotes (N:N) do utilizador.
     *
     * uq_user_batch é único por (user_id, batch_id) independentemente de
     * deleted_at, por isso nunca inserimos um par que já exista como
     * soft-deleted — "ressuscitamos" a linha antiga em vez de duplicar,
     * evitando violar a constraint.
     */
    public function syncBatches(int $userId, array $desiredBatchIds, int $actingUserId): void
    {
        $desired = array_unique(array_map('intval', $desiredBatchIds));
        $current = $this->batchIdsForUser($userId);

        $toRemove = array_diff($current, $desired);
        $toAdd = array_diff($desired, $current);

        if ($toRemove !== []) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            $stmt = $this->pdo->prepare("
                UPDATE tb_user_batch SET deleted_at = NOW(), deleted_by = ?
                WHERE user_id = ? AND deleted_at IS NULL AND batch_id IN ({$placeholders})
            ");
            $stmt->execute([$actingUserId, $userId, ...array_values($toRemove)]);
        }

        $reviveStmt = $this->pdo->prepare('
            UPDATE tb_user_batch SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :acting_user
            WHERE user_id = :user_id AND batch_id = :batch_id AND deleted_at IS NOT NULL
        ');
        $insertStmt = $this->pdo->prepare('
            INSERT INTO tb_user_batch (uuid, user_id, batch_id, created_at, created_by)
            VALUES (UUID(), :user_id, :batch_id, NOW(), :created_by)
        ');

        foreach ($toAdd as $batchId) {
            $reviveStmt->execute(['user_id' => $userId, 'batch_id' => $batchId, 'acting_user' => $actingUserId]);

            if ($reviveStmt->rowCount() === 0) {
                $insertStmt->execute(['user_id' => $userId, 'batch_id' => $batchId, 'created_by' => $actingUserId]);
            }
        }
    }

    public function batchIdsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT batch_id FROM tb_user_batch WHERE user_id = :user_id AND deleted_at IS NULL
        ');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'batch_id'));
    }
}
