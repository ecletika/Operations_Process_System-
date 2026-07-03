<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0027 / RN-0037 a RN-0039 - tb_audit nunca é editada nem apagada.
 * Este repositório é só de leitura por desenho.
 */
final class AuditRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function list(?string $tableName, ?int $userId, int $limit = 100): array
    {
        $conditions = [];
        $params = [];

        if ($tableName !== null && $tableName !== '') {
            $conditions[] = 'a.table_name = :table_name';
            $params['table_name'] = $tableName;
        }

        if ($userId !== null) {
            $conditions[] = 'a.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.first_name, u.last_name
            FROM tb_audit a
            LEFT JOIN tb_user u ON u.id = a.user_id
            {$where}
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function distinctTables(): array
    {
        return array_column($this->pdo->query('
            SELECT DISTINCT table_name FROM tb_audit ORDER BY table_name ASC
        ')->fetchAll(), 'table_name');
    }
}
