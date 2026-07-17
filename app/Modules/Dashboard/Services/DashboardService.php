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
        $sql = "
            SELECT COUNT(*) FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL
              AND st.code NOT IN ('SOLVED', 'CLOSED')
              AND (
                    (pr.default_sla_minutes IS NOT NULL AND TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) > pr.default_sla_minutes)
                    OR p.reopen_count >= 2
              )
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND p.assigned_to = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function slaNearCount(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL
              AND p.assigned_to = :user_id
              AND st.code NOT IN ('SOLVED', 'CLOSED')
              AND pr.default_sla_minutes IS NOT NULL
              AND (pr.default_sla_minutes - TIMESTAMPDIFF(MINUTE, p.created_at, NOW())) BETWEEN 0 AND 15
        ");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
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

        $slaRow = $this->pdo->query("
            SELECT
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at) <= pr.default_sla_minutes THEN 1 ELSE 0 END) AS met,
                COUNT(*) AS total,
                AVG(TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at)) AS avg_minutes
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL AND s.code IN ('SOLVED', 'CLOSED') AND DATE(p.closed_at) = CURDATE()
        ")->fetch();

        $slaPercentage = ($slaRow && (int) $slaRow['total'] > 0)
            ? round(((int) $slaRow['met'] / (int) $slaRow['total']) * 100, 1)
            : null;

        $avgResolutionMinutes = $slaRow && $slaRow['avg_minutes'] !== null ? (int) round((float) $slaRow['avg_minutes']) : null;

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
     * §8.8 - Ranking de operadores (últimos 30 dias).
     */
    private function ranking(): array
    {
        return $this->pdo->query("
            SELECT u.first_name, u.last_name,
                   COUNT(*) AS closed_total,
                   AVG(TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at)) AS avg_minutes
            FROM tb_process p
            JOIN tb_user u ON u.id = p.closed_by
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL
              AND s.code IN ('SOLVED', 'CLOSED')
              AND p.closed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id, u.first_name, u.last_name
            ORDER BY closed_total DESC
            LIMIT 5
        ")->fetchAll();
    }
}
