<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use App\Helpers\PlateHelper;
use PDO;
use PDOException;

/**
 * OPS-PRD-001 5.6 / TABELA 11 - matrícula é índice único e normalizado.
 */
final class VehicleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByPlate(string $plate): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_vehicle WHERE plate = :plate AND deleted_at IS NULL LIMIT 1
        ');
        $stmt->execute(['plate' => PlateHelper::normalize($plate)]);
        $vehicle = $stmt->fetch();

        return $vehicle ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_vehicle WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $vehicle = $stmt->fetch();

        return $vehicle ?: null;
    }

    /**
     * "Find-or-create" resiliente a corrida: se dois pedidos concorrentes
     * (ex.: duplo clique / reenvio) tentarem criar a mesma matrícula em
     * simultâneo, o INSERT do segundo falha por violar uq_vehicle_plate —
     * em vez de propagar o erro, devolvemos o registo que o primeiro
     * pedido acabou de criar.
     */
    public function create(string $plate, int $customerId, int $userId, ?string $brand = null, ?string $model = null, ?int $year = null): int
    {
        $normalizedPlate = PlateHelper::normalize($plate);

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO tb_vehicle (uuid, plate, customer_id, brand, model, year, active, created_at, created_by)
                VALUES (UUID(), :plate, :customer_id, :brand, :model, :year, 1, NOW(), :created_by)
            ');
            $stmt->execute([
                'plate' => $normalizedPlate,
                'customer_id' => $customerId,
                'brand' => $brand,
                'model' => $model,
                'year' => $year,
                'created_by' => $userId,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }

            $existing = $this->findByPlate($normalizedPlate);
            if ($existing !== null) {
                return (int) $existing['id'];
            }

            // A matrícula existe mas está soft-deleted (ex.: foi para a
            // Lixeira) — revivemos o registo em vez de duplicar, evitando
            // violar uq_vehicle_plate (que ignora deleted_at).
            $deleted = $this->findByPlateIncludingDeleted($normalizedPlate);
            if ($deleted === null) {
                throw $e;
            }

            $revive = $this->pdo->prepare('
                UPDATE tb_vehicle
                SET customer_id = :customer_id, brand = :brand, model = :model, year = :year,
                    deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :updated_by
                WHERE id = :id
            ');
            $revive->execute([
                'id' => $deleted['id'],
                'customer_id' => $customerId,
                'brand' => $brand,
                'model' => $model,
                'year' => $year,
                'updated_by' => $userId,
            ]);

            return (int) $deleted['id'];
        }
    }

    private function findByPlateIncludingDeleted(string $normalizedPlate): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_vehicle WHERE plate = :plate LIMIT 1');
        $stmt->execute(['plate' => $normalizedPlate]);
        $vehicle = $stmt->fetch();

        return $vehicle ?: null;
    }

    /**
     * Lista de Viaturas com pesquisa por matrícula/marca/modelo e aba
     * "Viaturas Recorrentes" (RN-0060: $threshold+ processos em $windowDays dias).
     */
    public function listAll(string $search, bool $onlyRecurrent, int $windowDays, int $threshold, int $limit = 300): array
    {
        $conditions = ['v.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $normalized = PlateHelper::normalize($search);
            $conditions[] = "(REGEXP_REPLACE(v.plate, '[^0-9A-Za-z]', '') LIKE :plate OR v.brand LIKE :brand OR v.model LIKE :model OR c.name LIKE :cname)";
            $params['plate'] = "%{$normalized}%";
            $params['brand'] = "%{$search}%";
            $params['model'] = "%{$search}%";
            $params['cname'] = "%{$search}%";
        }

        $having = $onlyRecurrent ? 'HAVING recent_processes >= :threshold' : '';
        if ($onlyRecurrent) {
            $params['threshold'] = $threshold;
        }

        $sql = '
            SELECT v.id, v.plate, v.brand, v.model, v.year, v.created_at,
                   c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
                   COUNT(DISTINCT p.id) AS process_count,
                   COUNT(DISTINCT CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY) THEN p.id END) AS recent_processes,
                   MAX(p.created_at) AS last_process_at
            FROM tb_vehicle v
            JOIN tb_customer c ON c.id = v.customer_id
            LEFT JOIN tb_process p ON p.vehicle_id = v.id AND p.deleted_at IS NULL
            WHERE ' . implode(' AND ', $conditions) . '
            GROUP BY v.id, v.plate, v.brand, v.model, v.year, v.created_at, c.id, c.name, c.phone
            ' . $having . '
            ORDER BY v.plate ASC
            LIMIT :limit
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('window_days', $windowDays, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Histórico da Viatura - todos os processos desta matrícula. */
    public function processesFor(int $vehicleId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.process_number, p.created_at, p.closed_at, p.contact_count, p.reopen_count,
                   sub.name AS subject_name,
                   st.name AS status_name, st.code AS status_code,
                   pr.name AS priority_name, pr.color AS priority_color,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name
            FROM tb_process p
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            WHERE p.vehicle_id = :vehicle_id AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['vehicle_id' => $vehicleId]);

        return $stmt->fetchAll();
    }

    public function update(int $id, ?string $brand, ?string $model, ?int $year, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_vehicle
            SET brand = :brand, model = :model, year = :year, updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'brand' => $brand, 'model' => $model, 'year' => $year, 'updated_by' => $userId]);
    }

    /** Soft-delete (RN-0048) — o registo fica na Lixeira, recuperável. */
    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_vehicle SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
