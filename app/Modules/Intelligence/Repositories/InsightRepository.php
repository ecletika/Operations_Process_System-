<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Repositories;

use App\Core\Database;
use App\Core\Settings;
use PDO;

/**
 * Inteligência Operacional™ (OPS-PRD-001 §7.18) - camada de leitura para o
 * Dashboard Executivo. Todos os tempos/SLA são calculados a partir dos
 * timestamps reais, nunca de valores armazenados (§10.20).
 *
 * Responsável apenas por Base de Dados (11.8) - sem regra de negócio.
 */
final class InsightRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * KPIs executivos do período (ou desde sempre se $from/$to = null).
     */
    public function kpis(?string $from, ?string $to): array
    {
        $where = 'p.deleted_at IS NULL';
        $params = [];

        if ($from !== null) {
            $where .= ' AND p.created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= ' AND p.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN s.code NOT IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS abertos,
                SUM(CASE WHEN s.code IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS concluidos,
                SUM(CASE WHEN p.reopen_count > 0 THEN 1 ELSE 0 END) AS reabertos,
                SUM(CASE WHEN pr.code = 'P1' AND s.code NOT IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS criticos_abertos,
                SUM(CASE WHEN p.closed_at IS NOT NULL AND pr.default_sla_minutes IS NOT NULL
                         THEN 1 ELSE 0 END) AS concluidos_com_sla
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        // O tempo médio e o cumprimento do SLA saem daqui em PHP, com a mesma
        // regra do Relatório SLA e do Dashboard — o TIMESTAMPDIFF ignorava o
        // horário de atendimento e dava um número diferente para o mesmo mês.
        [$tempoMedio, $dentroSla] = $this->slaFechados($where, $params);

        $slaTotal = (int) ($row['concluidos_com_sla'] ?? 0);
        $slaPct = $slaTotal > 0 ? (int) round(($dentroSla / $slaTotal) * 100) : null;

        return [
            'total' => (int) ($row['total'] ?? 0),
            'abertos' => (int) ($row['abertos'] ?? 0),
            'concluidos' => (int) ($row['concluidos'] ?? 0),
            'reabertos' => (int) ($row['reabertos'] ?? 0),
            'criticos_abertos' => (int) ($row['criticos_abertos'] ?? 0),
            'tempo_medio_min' => $tempoMedio,
            'sla_pct' => $slaPct,
        ];
    }

    /**
     * Tendência diária dos últimos N dias: criados vs concluídos.
     *
     * @return array<string, array{created:int, resolved:int}> indexado por data (Y-m-d)
     */
    public function dailyTrend(int $days = 14): array
    {
        $created = $this->pdo->prepare("
            SELECT DATE(created_at) AS d, COUNT(*) AS total
            FROM tb_process
            WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
        ");
        $created->execute(['days' => $days]);
        $createdByDay = array_column($created->fetchAll(), 'total', 'd');

        $resolved = $this->pdo->prepare("
            SELECT DATE(closed_at) AS d, COUNT(*) AS total
            FROM tb_process
            WHERE deleted_at IS NULL AND closed_at IS NOT NULL
              AND closed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(closed_at)
        ");
        $resolved->execute(['days' => $days]);
        $resolvedByDay = array_column($resolved->fetchAll(), 'total', 'd');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $series[$day] = [
                'created' => (int) ($createdByDay[$day] ?? 0),
                'resolved' => (int) ($resolvedByDay[$day] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Tempo médio de finalização e número de processos dentro do SLA, para o
     * mesmo filtro do resumo. Em PHP porque os minutos contam com o horário de
     * atendimento (ver sla_elapsed_minutes).
     *
     * @return array{0: ?int, 1: int} [tempo médio em minutos, dentro do SLA]
     */
    private function slaFechados(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.created_at, p.closed_at, p.sla_closed_minutes, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE {$where} AND p.closed_at IS NOT NULL
        ");
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();

        if ($linhas === []) {
            return [null, 0];
        }

        $soma = 0;
        $dentro = 0;
        foreach ($linhas as $linha) {
            $minutos = sla_process_minutes($linha);
            $soma += $minutos;
            $sla = $linha['default_sla_minutes'];
            if ($sla !== null && $sla !== '' && $minutos <= (int) $sla) {
                $dentro++;
            }
        }

        return [(int) round($soma / count($linhas)), $dentro];
    }

    /**
     * Gargalos Operacionais: processos abertos por estado, com o tempo médio
     * (em minutos) que estão parados desde a última atividade na Timeline Viva™.
     */
    public function bottlenecks(): array
    {
        return $this->pdo->query("
            SELECT s.name AS estado, s.code AS estado_code,
                   COUNT(*) AS total,
                   AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ult.ultima, p.created_at), NOW())) AS parado_medio_min
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            LEFT JOIN (
                SELECT process_id, MAX(created_at) AS ultima
                FROM tb_timeline WHERE deleted_at IS NULL GROUP BY process_id
            ) ult ON ult.process_id = p.id
            WHERE p.deleted_at IS NULL AND s.code NOT IN ('SOLVED', 'CLOSED')
            GROUP BY s.id, s.name, s.code
            ORDER BY parado_medio_min DESC
        ")->fetchAll();
    }

    /**
     * Distribuição por Assunto (estatística) - volume e concluídos.
     */
    public function bySubject(?string $from, ?string $to): array
    {
        $where = 'p.deleted_at IS NULL';
        $params = [];
        if ($from !== null) {
            $where .= ' AND p.created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= ' AND p.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare("
            SELECT sub.name AS assunto, COUNT(*) AS total
            FROM tb_process p
            JOIN tb_subject sub ON sub.id = p.subject_id
            WHERE {$where}
            GROUP BY sub.id, sub.name
            ORDER BY total DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Processos Críticos abertos (prioridade P1) - lista acionável.
     */
    public function criticalOpen(int $limit = 15): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.process_number, c.name AS customer_name, v.plate AS vehicle_plate,
                   sub.name AS subject_name, s.name AS status_name,
                   TIMESTAMPDIFF(HOUR, p.created_at, NOW()) AS horas_aberto,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            WHERE p.deleted_at IS NULL AND pr.code = 'P1' AND s.code NOT IN ('SOLVED', 'CLOSED')
            ORDER BY p.created_at ASC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * RN-0059 - Clientes Frequentes: >= limiar de processos na janela.
     */
    public function frequentCustomers(int $limit = 10): array
    {
        $threshold = (int) Settings::get('frequent_customer_threshold', 5);
        $windowDays = (int) Settings::get('recurrence_window_days', 90);

        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.phone, c.email, COUNT(*) AS total
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            WHERE p.deleted_at IS NULL
              AND p.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            GROUP BY c.id, c.name, c.phone, c.email
            HAVING total >= :threshold
            ORDER BY total DESC
            LIMIT :limit
        ");
        $stmt->bindValue('window_days', $windowDays, PDO::PARAM_INT);
        $stmt->bindValue('threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * RN-0060 - Viaturas Recorrentes: >= limiar de processos na janela.
     */
    public function recurrentVehicles(int $limit = 10): array
    {
        $threshold = (int) Settings::get('recurrent_vehicle_threshold', 3);
        $windowDays = (int) Settings::get('recurrence_window_days', 90);

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.plate, COUNT(*) AS total
            FROM tb_process p
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            WHERE p.deleted_at IS NULL
              AND p.created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            GROUP BY v.id, v.plate
            HAVING total >= :threshold
            ORDER BY total DESC
            LIMIT :limit
        ");
        $stmt->bindValue('window_days', $windowDays, PDO::PARAM_INT);
        $stmt->bindValue('threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
