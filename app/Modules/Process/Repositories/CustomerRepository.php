<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 5.5 - Um cliente pode possuir várias viaturas.
 * Responsável apenas por Base de Dados (11.8) - sem regra de negócio.
 */
final class CustomerRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_customer WHERE phone = :phone AND deleted_at IS NULL LIMIT 1
        ');
        $stmt->execute(['phone' => $phone]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_customer WHERE email = :email AND deleted_at IS NULL LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_customer WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    /**
     * Um cliente pode ter sido identificado só por telefone ou só por email
     * (RF-0009: Tipo de Interação Email/Presencial pode não trazer telefone).
     */
    public function create(string $name, ?string $phone, ?string $email, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_customer (uuid, name, phone, email, active, created_at, created_by)
            VALUES (UUID(), :name, :phone, :email, 1, NOW(), :created_by)
        ');
        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lista de Clientes com pesquisa e aba "Clientes Frequentes" (RN-0059:
     * frequente = $threshold+ processos na janela de $windowDays dias).
     */
    public function listAll(string $search, bool $onlyFrequent, int $windowDays, int $threshold, int $limit = 300): array
    {
        $conditions = ['c.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(c.name LIKE :search1 OR c.phone LIKE :search2 OR c.email LIKE :search3)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
        }

        $having = $onlyFrequent ? 'HAVING recent_processes >= :threshold' : '';
        if ($onlyFrequent) {
            $params['threshold'] = $threshold;
        }

        $sql = '
            SELECT c.id, c.name, c.phone, c.email, c.created_at,
                   COUNT(DISTINCT v.id) AS vehicle_count,
                   COUNT(DISTINCT p.id) AS process_count,
                   COUNT(DISTINCT CASE WHEN p.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY) THEN p.id END) AS recent_processes,
                   MAX(p.created_at) AS last_process_at
            FROM tb_customer c
            LEFT JOIN tb_vehicle v ON v.customer_id = c.id AND v.deleted_at IS NULL
            LEFT JOIN tb_process p ON p.customer_id = c.id AND p.deleted_at IS NULL
            WHERE ' . implode(' AND ', $conditions) . '
            GROUP BY c.id, c.name, c.phone, c.email, c.created_at
            ' . $having . '
            ORDER BY c.name ASC
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

    /** Histórico do Cliente - viaturas dele. */
    public function vehiclesFor(int $customerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_vehicle WHERE customer_id = :customer_id AND deleted_at IS NULL ORDER BY plate ASC
        ');
        $stmt->execute(['customer_id' => $customerId]);

        return $stmt->fetchAll();
    }

    /** Histórico do Cliente - processos dele. */
    public function processesFor(int $customerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.process_number, p.created_at, p.closed_at, p.contact_count,
                   v.plate AS vehicle_plate, sub.name AS subject_name,
                   st.name AS status_name, st.code AS status_code,
                   pr.name AS priority_name, pr.color AS priority_color
            FROM tb_process p
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.customer_id = :customer_id AND p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['customer_id' => $customerId]);

        return $stmt->fetchAll();
    }

    /** Histórico de Contactos do cliente - interações de todos os processos dele. */
    public function interactionsFor(int $customerId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.received_at, i.interaction_type, i.channel, i.description,
                   p.id AS process_id, p.process_number,
                   u.first_name, u.last_name
            FROM tb_interaction i
            JOIN tb_process p ON p.id = i.process_id AND p.deleted_at IS NULL
            JOIN tb_user u ON u.id = i.operator_id
            WHERE p.customer_id = :customer_id AND i.deleted_at IS NULL
            ORDER BY i.received_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function update(int $id, string $name, string $phone, ?string $email, ?string $nif, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_customer
            SET name = :name, phone = :phone, email = :email, nif = :nif, updated_at = NOW(), updated_by = :updated_by
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email, 'nif' => $nif, 'updated_by' => $userId]);
    }

    /** Soft-delete (RN-0048) — o registo fica na Lixeira, recuperável. */
    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_customer SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
