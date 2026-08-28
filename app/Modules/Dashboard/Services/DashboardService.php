<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use App\Core\Database;
use PDO;

/**
 * Centro de Operações™ (OPS-PRD-001 capítulo 8).
 * "Se o operador precisa procurar trabalho, o sistema falhou.
 *  O trabalho deve encontrar o operador."
 */
final class DashboardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * §8.3 - Dashboard do Operador: central de trabalho, não uma tabela.
     */
    public function operatorWidgets(int $userId, ?int $batchId): array
    {
        return [
            'critical_count' => $this->criticalCount($userId),
            'sla_near_count' => $this->slaNearCount($userId),
            'my_processes_count' => $this->myProcessesCount($userId),
            'waiting_count' => $this->waitingCount($userId),
            'next_to_work' => $this->nextToWork($batchId),
            'inbox' => $this->inbox($userId),
        ];
    }

    /**
     * §8.5 - Dashboard Supervisor: controla toda a operação.
     */
    public function supervisorWidgets(): array
    {
        return array_merge($this->operationSummary(), [
            'ranking' => $this->ranking(),
            'critical_count' => $this->criticalCount(null),
        ]);
    }

    /**
     * §8.6 - Dashboard Administrador: mais estratégico.
     */
    public function adminWidgets(): array
    {
        return $this->supervisorWidgets();
    }

    private function criticalCount(?int $userId): int
    {
        // RN-0058 (simplificado p/ v1): ultrapassou o SLA ou já foi reaberto 2+ vezes.
        // Traz as linhas em vez de contar em SQL: o "ultrapassou o SLA" tem de
        // usar a mesma regra do resto do sistema (sla_elapsed_minutes), que o
        // TIMESTAMPDIFF não sabe aplicar. São só processos em aberto.
        $sql = "
            SELECT p.created_at, p.closed_at, p.sla_closed_minutes, p.reopen_count, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL
              AND st.code NOT IN ('SOLVED', 'CLOSED')
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND p.assigned_to = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $agora = time();
        $criticos = 0;
        foreach ($stmt->fetchAll() as $linha) {
            $sla = $linha['default_sla_minutes'];
            $estourou = $sla !== null && $sla !== ''
                && sla_process_minutes($linha) > (int) $sla;

            if ($estourou || (int) $linha['reopen_count'] >= 2) {
                $criticos++;
            }
        }

        return $criticos;
    }

    private function slaNearCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT p.created_at, p.closed_at, p.sla_closed_minutes, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL
              AND p.assigned_to = :user_id
              AND st.code NOT IN ('SOLVED', 'CLOSED')
              AND pr.default_sla_minutes IS NOT NULL
        ");
        $stmt->execute(['user_id' => $userId]);

        // "Falta pouco" tem de ser medido em minutos de atendimento: senão o
        // aviso disparava de madrugada, com o relógio do SLA parado.
        $agora = time();
        $emRisco = 0;
        foreach ($stmt->fetchAll() as $linha) {
            $falta = (int) $linha['default_sla_minutes'] - sla_process_minutes($linha);
            if ($falta >= 0 && $falta <= 15) {
                $emRisco++;
            }
        }

        return $emRisco;
    }

    private function myProcessesCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            WHERE p.deleted_at IS NULL AND p.assigned_to = :user_id AND st.code NOT IN ('CLOSED')
        ");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    private function waitingCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            WHERE p.deleted_at IS NULL
              AND p.assigned_to = :user_id
              AND st.is_waiting = 1
        ");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * §8.3 - "Próximo Processo a Trabalhar": topo da Fila Inteligente™.
     */
    private function nextToWork(?int $batchId): ?array
    {
        $sql = "
            SELECT p.id, p.process_number, p.created_at,
                   c.name AS customer_name, v.plate AS vehicle_plate,
                   pr.name AS priority_name, pr.color AS priority_color
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL AND st.code = 'QUEUE'
        ";
        $params = [];
        if ($batchId !== null) {
            $sql .= ' AND p.batch_id = :batch_id';
            $params['batch_id'] = $batchId;
        }
        $sql .= ' ORDER BY pr.sort_order ASC, p.created_at ASC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $next = $stmt->fetch();

        return $next ?: null;
    }

    /**
     * §8.4 - Minha Caixa de Entrada™: eventos recentes dos meus processos.
     */
    private function inbox(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.title, t.description, t.icon, t.color, t.created_at, p.process_number, p.id AS process_id
            FROM tb_timeline t
            JOIN tb_process p ON p.id = t.process_id
            WHERE p.deleted_at IS NULL AND p.assigned_to = :user_id AND t.deleted_at IS NULL
            ORDER BY t.created_at DESC
            LIMIT 8
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    private function operationSummary(): array
    {
        $createdToday = (int) $this->pdo->query('
            SELECT COUNT(*) FROM tb_process WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()
        ')->fetchColumn();

        $inQueue = (int) $this->pdo->query("
            SELECT COUNT(*) FROM tb_process p JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL AND s.code = 'QUEUE'
        ")->fetchColumn();

        $assignedOpen = (int) $this->pdo->query("
            SELECT COUNT(*) FROM tb_process p JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL AND s.code NOT IN ('QUEUE', 'SOLVED', 'CLOSED') AND p.assigned_to IS NOT NULL
        ")->fetchColumn();

        $closedToday = (int) $this->pdo->query("
            SELECT COUNT(*) FROM tb_process p JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL AND s.code IN ('SOLVED', 'CLOSED') AND DATE(p.closed_at) = CURDATE()
        ")->fetchColumn();

        // Fechados hoje, um a um: a % de cumprimento e o tempo médio têm de bater
        // certo com o Relatório SLA — são os números que sustentam os prémios.
        $fechadosHoje = $this->pdo->query("
            SELECT p.created_at, p.closed_at, p.sla_closed_minutes, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL AND s.code IN ('SOLVED', 'CLOSED') AND DATE(p.closed_at) = CURDATE()
        ")->fetchAll();

        $met = 0;
        $somaMinutos = 0;
        foreach ($fechadosHoje as $linha) {
            $minutos = sla_process_minutes($linha);
            $somaMinutos += $minutos;
            $sla = $linha['default_sla_minutes'];
            if ($sla !== null && $sla !== '' && $minutos <= (int) $sla) {
                $met++;
            }
        }

        $totalFechados = count($fechadosHoje);
        $slaPercentage = $totalFechados > 0 ? round(($met / $totalFechados) * 100, 1) : null;
        $avgResolutionMinutes = $totalFechados > 0 ? (int) round($somaMinutos / $totalFechados) : null;

        $operatorsOnline = (int) $this->pdo->query('
            SELECT COUNT(*) FROM tb_user
            WHERE deleted_at IS NULL AND active = 1 AND last_login_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ')->fetchColumn();

        return [
            'created_today' => $createdToday,
            'in_queue' => $inQueue,
            'assigned_open' => $assignedOpen,
            'closed_today' => $closedToday,
            'sla_percentage' => $slaPercentage,
            'avg_resolution_minutes' => $avgResolutionMinutes,
            'operators_online' => $operatorsOnline,
        ];
    }

    /**
     * Painel de Filas por Departamento (supervisores). Para cada departamento
     * mostra quantos processos tem na fila e quantos foram assumidos hoje, e
     * lista os nºs dos processos em fila (para o "expandir"). Assim um
     * supervisor vê de relance onde está o trabalho e localiza um processo que
     * tenha ido para a fila errada.
     *
     * @param array<int>|null $departmentIds null = todos; caso contrário
     *        limita ao âmbito do utilizador (Supervisor de Departamento).
     * @return array<int, array<string, mixed>>
     */
    public function departmentBoard(?array $departmentIds = null): array
    {
        $where = 'd.deleted_at IS NULL';
        $params = [];
        if ($departmentIds !== null) {
            if ($departmentIds === []) {
                return [];
            }
            $ph = [];
            foreach (array_values($departmentIds) as $i => $id) {
                $ph[] = ':d' . $i;
                $params['d' . $i] = (int) $id;
            }
            $where .= ' AND d.id IN (' . implode(', ', $ph) . ')';
        }

        // Contagens por departamento (fila + assumidos hoje).
        $stmt = $this->pdo->prepare("
            SELECT d.id AS department_id, br.name AS branch_name, d.name AS department_name,
                   SUM(CASE WHEN st.code = 'QUEUE' THEN 1 ELSE 0 END) AS queue_count,
                   SUM(CASE WHEN p.assumed_at IS NOT NULL AND DATE(p.assumed_at) = CURDATE()
                            AND st.code NOT IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS assumed_today
            FROM tb_department d
            JOIN tb_branch br ON br.id = d.branch_id
            LEFT JOIN tb_batch bt ON bt.department_id = d.id
            LEFT JOIN tb_process p ON p.batch_id = bt.id AND p.deleted_at IS NULL
            LEFT JOIN tb_status st ON st.id = p.status_id
            WHERE {$where}
            GROUP BY d.id, br.name, d.name
            HAVING queue_count > 0 OR assumed_today > 0
            ORDER BY queue_count DESC, br.name ASC, d.name ASC
        ");
        $stmt->execute($params);
        $departments = $stmt->fetchAll();

        if ($departments === []) {
            return [];
        }

        // Nºs dos processos em fila, por departamento (para o expandir).
        $ids = array_map(static fn ($d) => (int) $d['department_id'], $departments);
        $ph = implode(', ', array_fill(0, count($ids), '?'));
        $qStmt = $this->pdo->prepare("
            SELECT d.id AS department_id, p.id, p.process_number,
                   pr.name AS priority_name, pr.color AS priority_color, p.created_at
            FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_batch bt ON bt.id = p.batch_id
            JOIN tb_department d ON d.id = bt.department_id
            WHERE p.deleted_at IS NULL AND st.code = 'QUEUE' AND d.id IN ({$ph})
            ORDER BY pr.sort_order ASC, p.created_at ASC
        ");
        $qStmt->execute($ids);

        $byDept = [];
        foreach ($qStmt->fetchAll() as $row) {
            $byDept[(int) $row['department_id']][] = $row;
        }

        foreach ($departments as &$dept) {
            $dept['queue_count'] = (int) $dept['queue_count'];
            $dept['assumed_today'] = (int) $dept['assumed_today'];
            $dept['queue_processes'] = $byDept[(int) $dept['department_id']] ?? [];
        }

        return $departments;
    }

    /**
     * §8.8 - Ranking de operadores (últimos 30 dias).
     */
    private function ranking(): array
    {
        $linhas = $this->pdo->query("
            SELECT u.id, u.first_name, u.last_name, p.created_at, p.closed_at, p.sla_closed_minutes
            FROM tb_process p
            JOIN tb_user u ON u.id = p.closed_by
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL
              AND s.code IN ('SOLVED', 'CLOSED')
              AND p.closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchAll();

        // O tempo médio do ranking conta em minutos de atendimento, como o
        // resto do SLA — senão quem fecha de manhã o que entrou à noite parece
        // lento sem o ser.
        $porOperador = [];
        foreach ($linhas as $linha) {
            $id = (int) $linha['id'];
            $porOperador[$id] ??= [
                'first_name' => $linha['first_name'],
                'last_name' => $linha['last_name'],
                'closed_total' => 0,
                'avg_minutes' => 0,
                '_soma' => 0,
            ];
            $porOperador[$id]['closed_total']++;
            $porOperador[$id]['_soma'] += sla_process_minutes($linha);
        }

        foreach ($porOperador as &$operador) {
            $operador['avg_minutes'] = $operador['_soma'] / $operador['closed_total'];
            unset($operador['_soma']);
        }
        unset($operador);

        usort($porOperador, static fn (array $a, array $b): int => $b['closed_total'] <=> $a['closed_total']);

        return array_slice(array_values($porOperador), 0, 5);
    }
}
