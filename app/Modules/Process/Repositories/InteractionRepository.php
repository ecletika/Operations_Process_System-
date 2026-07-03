<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

final class InteractionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $processId, string $type, string $channel, string $description, int $operatorId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_interaction
                (uuid, process_id, interaction_type, channel, description, operator_id, received_at, created_at, created_by)
            VALUES
                (UUID(), :process_id, :type, :channel, :description, :operator_id, NOW(), NOW(), :created_by)
        ');
        $stmt->execute([
            'process_id' => $processId,
            'type' => $type,
            'channel' => $channel,
            'description' => $description,
            'operator_id' => $operatorId,
            'created_by' => $operatorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lista global de Interações (Histórico de Contactos) com filtros
     * combináveis: canal (PHONE/EMAIL/...), operador e intervalo de datas.
     */
    public function filterAll(array $filters, int $limit = 300): array
    {
        $conditions = ['i.deleted_at IS NULL', 'p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['channel'])) {
            $conditions[] = 'i.channel = :channel';
            $params['channel'] = $filters['channel'];
        }

        if (!empty($filters['operator_id'])) {
            $conditions[] = 'i.operator_id = :operator_id';
            $params['operator_id'] = (int) $filters['operator_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'i.received_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'i.received_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = '
            SELECT i.id, i.received_at, i.interaction_type, i.channel, i.description, i.duration_seconds,
                   p.id AS process_id, p.process_number,
                   c.name AS customer_name,
                   u.first_name, u.last_name
            FROM tb_interaction i
            JOIN tb_process p ON p.id = i.process_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_user u ON u.id = i.operator_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY i.received_at DESC
            LIMIT :limit
        ';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Canais distintos existentes, para o filtro da lista global. */
    public function distinctChannels(): array
    {
        return array_column($this->pdo->query('
            SELECT DISTINCT channel FROM tb_interaction WHERE deleted_at IS NULL ORDER BY channel ASC
        ')->fetchAll(), 'channel');
    }

    /** RF-0017 - ordenadas cronologicamente. */
    public function listByProcess(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*, u.first_name, u.last_name
            FROM tb_interaction i
            JOIN tb_user u ON u.id = i.operator_id
            WHERE i.process_id = :process_id AND i.deleted_at IS NULL
            ORDER BY i.received_at ASC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }
}
