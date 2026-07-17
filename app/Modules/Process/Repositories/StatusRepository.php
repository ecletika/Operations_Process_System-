<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0045 - Configurar Estados. Os códigos do fluxo principal (Fila →
 * Assumido → Em Tratamento → Resolvido…) são fixos, porque a máquina de
 * estados depende deles. Já os "Motivos de Pausa do SLA" (is_waiting = 1)
 * são livres: o Administrador cria os que quiser e a máquina de estados
 * trata-os todos da mesma forma.
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

    /**
     * Motivos de Pausa do SLA (estados que param o relógio).
     * $onlyActive=true para o que se mostra ao operador.
     */
    public function listWaiting(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM tb_status WHERE is_waiting = 1 AND deleted_at IS NULL';
        if ($onlyActive) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return string[] códigos dos motivos de pausa ativos */
    public function waitingCodes(bool $onlyActive = true): array
    {
        return array_column($this->listWaiting($onlyActive), 'code');
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_status WHERE code = :code AND deleted_at IS NULL');
        $stmt->execute(['code' => $code]);
        $status = $stmt->fetch();

        return $status ?: null;
    }

    /** Cria um Motivo de Pausa do SLA (estado com is_waiting = 1). */
    public function createWaiting(string $code, string $name, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_status (uuid, code, name, sort_order, is_waiting, active, created_at, created_by)
            VALUES (UUID(), :code, :name,
                    (SELECT COALESCE(MAX(s.sort_order), 0) + 1 FROM (SELECT sort_order FROM tb_status) s),
                    1, 1, NOW(), :user_id)
        ');
        $stmt->execute(['code' => $code, 'name' => $name, 'user_id' => $userId]);
    }

    /** Soft-delete de um Motivo de Pausa (RN-0048) — só se não estiver em uso. */
    public function deleteWaiting(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_status SET deleted_at = NOW(), deleted_by = :user_id
            WHERE id = :id AND is_waiting = 1
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /** Há processos (mesmo concluídos) neste estado? Evita apagar histórico. */
    public function isInUse(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tb_process WHERE status_id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);

        return ((int) $stmt->fetchColumn()) > 0;
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
