<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Core\Database;
use PDO;

/**
 * Módulo Relatórios (OPS-UI-001 · 📊): SLA, Produtividade/Operadores,
 * Lotes, Clientes, Viaturas, Reaberturas e Heatmap de Contactos.
 * Todas as queries aceitam um período opcional sobre p.created_at.
 */
final class AnalyticsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** WHERE de período partilhado. @return array{0:string,1:array} */
    private function periodClause(?string $from, ?string $to, string $column = 'p.created_at'): array
    {
        $conditions = [];
        $params = [];

        if ($from !== null && $from !== '') {
            $conditions[] = "{$column} >= :period_from";
            $params['period_from'] = $from . ' 00:00:00';
        }

        if ($to !== null && $to !== '') {
            $conditions[] = "{$column} <= :period_to";
            $params['period_to'] = $to . ' 23:59:59';
        }

        return [$conditions === [] ? '' : (' AND ' . implode(' AND ', $conditions)), $params];
    }

    private function run(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Filtro opcional por operador(es). Devolve [cláusula SQL, params].
     *
     * @param int[] $operatorIds
     * @return array{0:string,1:array}
     */
    private function operatorClause(array $operatorIds, string $column): array
    {
        $operatorIds = array_values(array_filter(array_map('intval', $operatorIds)));
        if ($operatorIds === []) {
            return ['', []];
        }

        $placeholders = [];
        $params = [];
        foreach ($operatorIds as $i => $id) {
            $placeholders[] = ":op{$i}";
            $params["op{$i}"] = $id;
        }

        return [' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }

    /**
     * Relatório SLA por colaborador × prioridade: % de processos concluídos
     * dentro do SLA (default_sla_minutes) — mostra quem está a cumprir.
     *
     * @param int[] $operatorIds filtro opcional (um ou mais operadores)
     */
    public function sla(?string $from, ?string $to, array $operatorIds = []): array
    {
        [$period, $params] = $this->periodClause($from, $to);
        [$opFilter, $opParams] = $this->operatorClause($operatorIds, 'p.closed_by');

        return $this->run("
            SELECT CONCAT(u.first_name, ' ', u.last_name) AS colaborador,
                   pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                   COUNT(p.id) AS concluidos,
                   SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at) <= pr.default_sla_minutes
                            THEN 1 ELSE 0 END) AS dentro_sla,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at)), 0) AS tempo_medio_min
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_user u ON u.id = p.closed_by
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL {$period}{$opFilter}
            GROUP BY u.id, u.first_name, u.last_name, pr.id, pr.name, pr.default_sla_minutes, pr.sort_order
            ORDER BY colaborador ASC, pr.sort_order ASC
        ", $params + $opParams);
    }

    /**
     * Produtividade por Operador: criados, assumidos, concluídos, tempo médio.
     *
     * @param int[] $operatorIds filtro opcional (um ou mais operadores)
     */
    public function operators(?string $from, ?string $to, array $operatorIds = []): array
    {
        [$periodCreated, $paramsCreated] = $this->periodClause($from, $to, 'p.created_at');
        [$periodAssigned, $paramsAssigned] = $this->periodClause($from, $to, 'p2.created_at');
        [$periodClosed, $paramsClosed] = $this->periodClause($from, $to, 'p3.created_at');
        [$opFilter, $opParams] = $this->operatorClause($operatorIds, 'u.id');

        // Renomeia parâmetros para não repetir o mesmo nome no statement
        $sql = "
            SELECT CONCAT(u.first_name, ' ', u.last_name) AS operador,
                   (SELECT COUNT(*) FROM tb_process p WHERE p.created_by = u.id AND p.deleted_at IS NULL {$periodCreated}) AS criados,
                   (SELECT COUNT(*) FROM tb_process p2 WHERE p2.assigned_to = u.id AND p2.deleted_at IS NULL " . str_replace(':period_', ':assigned_period_', $periodAssigned) . ") AS assumidos,
                   (SELECT COUNT(*) FROM tb_process p3 WHERE p3.closed_by = u.id AND p3.deleted_at IS NULL " . str_replace(':period_', ':closed_period_', $periodClosed) . ") AS concluidos,
                   (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, p4.assumed_at, p4.closed_at)), 0)
                    FROM tb_process p4
                    WHERE p4.closed_by = u.id AND p4.assumed_at IS NOT NULL AND p4.closed_at IS NOT NULL AND p4.deleted_at IS NULL) AS tempo_medio_min
            FROM tb_user u
            WHERE u.deleted_at IS NULL AND u.active = 1 {$opFilter}
            ORDER BY concluidos DESC, criados DESC
        ";

        $params = $paramsCreated + $opParams;
        foreach ($paramsAssigned as $key => $value) {
            $params[str_replace('period_', 'assigned_period_', $key)] = $value;
        }
        foreach ($paramsClosed as $key => $value) {
            $params[str_replace('period_', 'closed_period_', $key)] = $value;
        }

        return $this->run($sql, $params);
    }

    /** Relatório por Lote (Filial · Departamento). */
    public function batches(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        return $this->run("
            SELECT CONCAT(br.name, ' · ', d.name) AS lote,
                   COUNT(p.id) AS total,
                   SUM(CASE WHEN st.code NOT IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS em_andamento,
                   SUM(CASE WHEN st.code IN ('SOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS concluidos,
                   SUM(p.reopen_count) AS reaberturas,
                   ROUND(AVG(CASE WHEN p.closed_at IS NOT NULL
                                  THEN TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at) END), 0) AS tempo_medio_min
            FROM tb_batch bt
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            LEFT JOIN tb_process p ON p.batch_id = bt.id AND p.deleted_at IS NULL {$period}
            LEFT JOIN tb_status st ON st.id = p.status_id
            WHERE bt.deleted_at IS NULL
            GROUP BY bt.id, br.name, d.name
            ORDER BY total DESC
        ", $params);
    }

    /** Relatório de Clientes: top clientes por nº de processos. */
    public function customers(?string $from, ?string $to, int $limit = 50): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $sql = "
            SELECT c.name AS cliente, c.phone AS telefone,
                   COUNT(p.id) AS processos,
                   SUM(p.contact_count) AS contactos,
                   SUM(p.reopen_count) AS reaberturas,
                   MAX(p.created_at) AS ultimo_processo
            FROM tb_customer c
            JOIN tb_process p ON p.customer_id = c.id AND p.deleted_at IS NULL {$period}
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.name, c.phone
            ORDER BY processos DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Relatório de Viaturas: top matrículas por nº de processos. */
    public function vehicles(?string $from, ?string $to, int $limit = 50): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $sql = "
            SELECT v.plate AS matricula, CONCAT(COALESCE(v.brand, ''), ' ', COALESCE(v.model, '')) AS viatura,
                   c.name AS cliente,
                   COUNT(p.id) AS processos,
                   SUM(p.reopen_count) AS reaberturas,
                   MAX(p.created_at) AS ultimo_processo
            FROM tb_vehicle v
            JOIN tb_customer c ON c.id = v.customer_id
            JOIN tb_process p ON p.vehicle_id = v.id AND p.deleted_at IS NULL {$period}
            WHERE v.deleted_at IS NULL
            GROUP BY v.id, v.plate, v.brand, v.model, c.name
            ORDER BY processos DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Relatório de Reaberturas: processos reabertos pelo menos uma vez. */
    public function reopened(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        return $this->run("
            SELECT p.id, p.process_number AS processo, p.reopen_count AS reaberturas,
                   c.name AS cliente, v.plate AS matricula,
                   sub.name AS assunto, st.name AS estado, p.created_at AS criado_em
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            WHERE p.deleted_at IS NULL AND p.reopen_count > 0 {$period}
            ORDER BY p.reopen_count DESC, p.created_at DESC
            LIMIT 200
        ", $params);
    }

    /**
     * Heatmap de Contactos: nº de interações por dia da semana × hora.
     * Devolve linhas (weekday 1=Segunda..7=Domingo, hour 0..23, total).
     */
    public function contactHeatmap(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to, 'i.received_at');

        return $this->run("
            SELECT WEEKDAY(i.received_at) + 1 AS weekday, HOUR(i.received_at) AS hour, COUNT(*) AS total
            FROM tb_interaction i
            WHERE i.deleted_at IS NULL " . ($period !== '' ? $period : '') . "
            GROUP BY weekday, hour
        ", $params);
    }
}
