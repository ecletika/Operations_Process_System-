<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * 🗑️ Lixeira / Reciclagem — lista e restaura registos soft-deleted
 * (RN-0048: nada é apagado fisicamente). Individualizado por registo, ao
 * contrário do backup do alojamento, que apanharia todos os clientes juntos.
 */
final class TrashRepository
{
    /** Entidades recuperáveis: chave => [tabela, coluna de rótulo, nome legível]. */
    private const ENTITIES = [
        'process' => ['table' => 'tb_process', 'label_col' => 'process_number', 'name' => 'Processos'],
        'customer' => ['table' => 'tb_customer', 'label_col' => 'name', 'name' => 'Clientes'],
        'vehicle' => ['table' => 'tb_vehicle', 'label_col' => 'plate', 'name' => 'Viaturas'],
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<string, array{name:string, rows:array}> agrupado por entidade */
    public function listDeleted(): array
    {
        $result = [];

        foreach (self::ENTITIES as $key => $meta) {
            $stmt = $this->pdo->query("
                SELECT t.id, t.{$meta['label_col']} AS label, t.deleted_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS deleted_by_name
                FROM {$meta['table']} t
                LEFT JOIN tb_user u ON u.id = t.deleted_by
                WHERE t.deleted_at IS NOT NULL
                ORDER BY t.deleted_at DESC
            ");
            $result[$key] = ['name' => $meta['name'], 'rows' => $stmt->fetchAll()];
        }

        return $result;
    }

    public function isValidEntity(string $entity): bool
    {
        return isset(self::ENTITIES[$entity]);
    }

    /** Restaura um registo (deleted_at -> NULL). */
    public function restore(string $entity, int $id, int $userId): bool
    {
        if (!$this->isValidEntity($entity)) {
            return false;
        }

        $table = self::ENTITIES[$entity]['table'];
        $stmt = $this->pdo->prepare("
            UPDATE {$table}
            SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :user_id
            WHERE id = :id AND deleted_at IS NOT NULL
        ");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /** Restaura tudo o que está na Lixeira. Devolve o total restaurado. */
    public function restoreAll(int $userId): int
    {
        $total = 0;

        foreach (self::ENTITIES as $meta) {
            $stmt = $this->pdo->prepare("
                UPDATE {$meta['table']}
                SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :user_id
                WHERE deleted_at IS NOT NULL
            ");
            $stmt->execute(['user_id' => $userId]);
            $total += $stmt->rowCount();
        }

        return $total;
    }
}
