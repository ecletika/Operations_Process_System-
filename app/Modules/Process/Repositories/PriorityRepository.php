<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

final class PriorityRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_priority WHERE active = 1 AND deleted_at IS NULL ORDER BY sort_order ASC
        ')->fetchAll();
    }

    /**
     * RF-0046 - Configurar Prioridades (inclui inativas, para gestão).
     */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_priority WHERE deleted_at IS NULL ORDER BY sort_order ASC
        ')->fetchAll();
    }

    /**
     * $nextContactAutoMinutes: minutos de atendimento entre contactos com o
     * cliente enquanto o SLA está em pausa. null = sem lembrete automático
     * (o operador escolhe a data no calendário do processo).
     */
    public function create(string $code, string $name, string $color, int $sortOrder, ?int $defaultSlaMinutes, ?int $nextContactAutoMinutes, int $userId): void
    {
        $temColuna = self::hasNextContactColumn();

        $stmt = $this->pdo->prepare('
            INSERT INTO tb_priority (uuid, code, name, color, sort_order, default_sla_minutes'
                . ($temColuna ? ', next_contact_auto_minutes' : '') . ', active, created_at, created_by)
            VALUES (UUID(), :code, :name, :color, :sort_order, :sla'
                . ($temColuna ? ', :auto_minutes' : '') . ', 1, NOW(), :user_id)
        ');

        $params = [
            'code' => $code, 'name' => $name, 'color' => $color,
            'sort_order' => $sortOrder, 'sla' => $defaultSlaMinutes, 'user_id' => $userId,
        ];
        if ($temColuna) {
            $params['auto_minutes'] = $nextContactAutoMinutes;
        }

        $stmt->execute($params);
    }

    public function update(int $id, string $name, string $color, int $sortOrder, ?int $defaultSlaMinutes, ?int $nextContactAutoMinutes, int $userId): void
    {
        $temColuna = self::hasNextContactColumn();

        $stmt = $this->pdo->prepare('
            UPDATE tb_priority
            SET name = :name, color = :color, sort_order = :sort_order, default_sla_minutes = :sla, '
                . ($temColuna ? 'next_contact_auto_minutes = :auto_minutes, ' : '') . '
                updated_at = NOW(), updated_by = :user_id
            WHERE id = :id
        ');

        $params = [
            'id' => $id, 'name' => $name, 'color' => $color,
            'sort_order' => $sortOrder, 'sla' => $defaultSlaMinutes, 'user_id' => $userId,
        ];
        if ($temColuna) {
            $params['auto_minutes'] = $nextContactAutoMinutes;
        }

        $stmt->execute($params);
    }

    /**
     * Ver ProcessRepository::nextContactColumn(): enquanto a migração 030 não
     * correr, guardar prioridades continua a funcionar — apenas sem o campo
     * do contacto periódico, que ainda não existe na base.
     */
    private static function hasNextContactColumn(): bool
    {
        return Database::hasColumn('tb_priority', 'next_contact_auto_minutes');
    }

    public function toggleActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_priority SET active = :active, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'user_id' => $userId]);
    }
}
