<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Core\Database;
use App\Helpers\PlateHelper;
use PDO;

/**
 * Dados do Relatório de Imobilizados. Só leitura, sem regra de negócio
 * (o cálculo de cumprimento vive em ImmobilizedComplianceCalculator).
 */
final class ImmobilizedReportRepository implements ImmobilizedReportSource
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Processos com o assunto de Imobilizado ($subjectCode), filtrados por
     * intervalo de datas (data de criação), matrícula e viatura (marca/modelo).
     *
     * @param array{from?:?string, to?:?string, plate?:string, vehicle?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function processes(string $subjectCode, array $filters, int $limit = 500): array
    {
        $conditions = ['p.deleted_at IS NULL', 'sub.code = :subject'];
        $params = ['subject' => $subjectCode];

        if (!empty($filters['from'])) {
            $conditions[] = 'p.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'p.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['plate'])) {
            $conditions[] = "REGEXP_REPLACE(v.plate, '[^0-9A-Za-z]', '') LIKE :plate";
            $params['plate'] = '%' . PlateHelper::normalize($filters['plate']) . '%';
        }
        if (!empty($filters['vehicle'])) {
            $conditions[] = '(v.brand LIKE :vehicle OR v.model LIKE :vehicle2)';
            $params['vehicle'] = '%' . $filters['vehicle'] . '%';
            $params['vehicle2'] = '%' . $filters['vehicle'] . '%';
        }

        $sql = '
            SELECT p.id, p.process_number, p.created_at, p.closed_at,
                   st.code AS status_code, st.name AS status_name,
                   pr.name AS priority_name, pr.color AS priority_color,
                   ' . self::deadlineExpr() . ' AS deadline_minutes,
                   c.name AS customer_name,
                   v.plate AS vehicle_plate, v.brand AS vehicle_brand, v.model AS vehicle_model,
                   u.first_name AS resp_first, u.last_name AS resp_last,
                   br.name AS branch_name, d.name AS department_name
            FROM tb_process p
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            LEFT JOIN tb_batch bt ON bt.id = p.batch_id
            LEFT JOIN tb_department d ON d.id = bt.department_id
            LEFT JOIN tb_branch br ON br.id = d.branch_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY p.created_at DESC
            LIMIT ' . max(1, $limit) . '
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Contactos (interações + observações) de um conjunto de processos, por
     * ordem cronológica. Uma Observação conta como contacto, tal como no resto
     * do sistema. Só a versão original de cada observação é contada (edições
     * posteriores têm edited_from preenchido e não são um novo contacto).
     *
     * @param list<int> $processIds
     * @return array<int, list<array{ts:string, kind:string, channel:string, who:string, text:string}>>
     *         agrupado por process_id
     */
    public function contactsByProcess(array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        $in = implode(',', array_map('intval', $processIds));
        $grouped = [];

        $interactions = $this->pdo->query("
            SELECT i.process_id, i.received_at AS ts, i.interaction_type, i.channel, i.description,
                   u.first_name, u.last_name
            FROM tb_interaction i
            JOIN tb_user u ON u.id = i.operator_id
            WHERE i.process_id IN ({$in}) AND i.deleted_at IS NULL
        ")->fetchAll();

        foreach ($interactions as $row) {
            $grouped[(int) $row['process_id']][] = [
                'ts' => (string) $row['ts'],
                'kind' => 'interaction',
                'channel' => trim((string) ($row['channel'] ?: $row['interaction_type'])),
                'who' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'text' => (string) ($row['description'] ?? ''),
            ];
        }

        $notes = $this->pdo->query("
            SELECT n.process_id, n.created_at AS ts, n.note, u.first_name, u.last_name
            FROM tb_note n
            JOIN tb_user u ON u.id = n.author_id
            WHERE n.process_id IN ({$in}) AND n.deleted_at IS NULL AND n.edited_from IS NULL
        ")->fetchAll();

        foreach ($notes as $row) {
            $grouped[(int) $row['process_id']][] = [
                'ts' => (string) $row['ts'],
                'kind' => 'note',
                'channel' => 'Observação',
                'who' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'text' => (string) ($row['note'] ?? ''),
            ];
        }

        // Ordena cada processo cronologicamente (ascendente).
        foreach ($grouped as &$list) {
            usort($list, static fn (array $a, array $b): int => strcmp($a['ts'], $b['ts']));
        }
        unset($list);

        return $grouped;
    }

    /**
     * Prazo (minutos) por processo: usa o intervalo configurado na Prioridade
     * (next_contact_auto_minutes) quando definido; senão, 960 (16h úteis), o
     * valor por omissão para Imobilizados. Resiliente à migração 030 ainda não
     * aplicada (a coluna pode não existir), tal como ProcessRepository.
     */
    private static function deadlineExpr(): string
    {
        return Database::hasColumn('tb_priority', 'next_contact_auto_minutes')
            ? 'COALESCE(NULLIF(pr.next_contact_auto_minutes, 0), 960)'
            : '960';
    }
}
