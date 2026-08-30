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
     * Filtro IN genérico (ex.: prioridades), com prefixo de parâmetro próprio
     * para não colidir com o filtro de operadores no mesmo statement.
     *
     * @param int[] $ids
     * @return array{0:string,1:array}
     */
    private function inClause(array $ids, string $column, string $prefix): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return ['', []];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $placeholders[] = ":{$prefix}{$i}";
            $params["{$prefix}{$i}"] = $id;
        }

        return [' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }

    /**
     * Relatório SLA: % de processos concluídos dentro do SLA
     * (default_sla_minutes) por prioridade — mostra quem/qual equipa cumpre.
     *
     * @param int[] $operatorIds filtro opcional (só no modo "colaborador")
     * @param int[] $priorityIds filtro opcional por prioridade
     * @param string $groupBy 'colaborador' (por omissão) ou 'equipa' (Filial · Departamento)
     */
    public function sla(?string $from, ?string $to, array $operatorIds = [], array $priorityIds = [], string $groupBy = 'colaborador'): array
    {
        // Com o horário de atendimento ligado o tempo decorrido deixa de ser
        // uma subtracção de datas, por isso a agregação passa para PHP. Com ele
        // desligado mantém-se o SQL agregado — mesmo resultado, mais rápido.
        if (\App\Modules\Process\Support\BusinessClock::enabled()) {
            return $this->slaEmHorarioUtil($from, $to, $operatorIds, $priorityIds, $groupBy);
        }

        [$period, $params] = $this->periodClause($from, $to);
        [$prFilter, $prParams] = $this->inClause($priorityIds, 'pr.id', 'pri');

        if ($groupBy === 'equipa') {
            return $this->run("
                SELECT CONCAT(br.name, ' · ', d.name) AS equipa,
                       pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                       COUNT(p.id) AS concluidos,
                       SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at) <= pr.default_sla_minutes
                                THEN 1 ELSE 0 END) AS dentro_sla,
                       ROUND(AVG(TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at)), 0) AS tempo_medio_min,
                       bt.id AS batch_id, pr.id AS priority_id
                FROM tb_process p
                JOIN tb_priority pr ON pr.id = p.priority_id
                JOIN tb_status st ON st.id = p.status_id
                JOIN tb_batch bt ON bt.id = p.batch_id
                JOIN tb_department d ON d.id = bt.department_id
                JOIN tb_branch br ON br.id = d.branch_id
                WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}{$prFilter}
                GROUP BY bt.id, br.name, d.name, pr.id, pr.name, pr.default_sla_minutes, pr.sort_order
                ORDER BY equipa ASC, pr.sort_order ASC
            ", $params + $prParams);
        }

        [$opFilter, $opParams] = $this->operatorClause($operatorIds, 'p.closed_by');

        return $this->run("
            SELECT CONCAT(u.first_name, ' ', u.last_name) AS colaborador,
                   pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                   COUNT(p.id) AS concluidos,
                   SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at) <= pr.default_sla_minutes
                            THEN 1 ELSE 0 END) AS dentro_sla,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, p.created_at, p.closed_at)), 0) AS tempo_medio_min,
                   u.id AS operator_id, pr.id AS priority_id
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_user u ON u.id = p.closed_by
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}{$opFilter}{$prFilter}
            GROUP BY u.id, u.first_name, u.last_name, pr.id, pr.name, pr.default_sla_minutes, pr.sort_order
            ORDER BY colaborador ASC, pr.sort_order ASC
        ", $params + $opParams + $prParams);
    }

    /**
     * Mesma leitura do Relatório SLA, mas com o relógio de negócio: traz um
     * registo por processo concluído e agrega em PHP, porque os minutos úteis
     * dependem do horário, do almoço e dos feriados — coisas que o
     * TIMESTAMPDIFF do SQL desconhece.
     *
     * @param int[] $operatorIds
     * @param int[] $priorityIds
     */
    private function slaEmHorarioUtil(?string $from, ?string $to, array $operatorIds, array $priorityIds, string $groupBy): array
    {
        [$period, $params] = $this->periodClause($from, $to);
        [$prFilter, $prParams] = $this->inClause($priorityIds, 'pr.id', 'pri');

        if ($groupBy === 'equipa') {
            $rows = $this->run("
                SELECT CONCAT(br.name, ' · ', d.name) AS equipa,
                       pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                       p.created_at, p.closed_at, p.sla_paused_total_minutes, p.sla_closed_minutes,
                       bt.id AS batch_id, pr.id AS priority_id
                FROM tb_process p
                JOIN tb_priority pr ON pr.id = p.priority_id
                JOIN tb_status st ON st.id = p.status_id
                JOIN tb_batch bt ON bt.id = p.batch_id
                JOIN tb_department d ON d.id = bt.department_id
                JOIN tb_branch br ON br.id = d.branch_id
                WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}{$prFilter}
                ORDER BY equipa ASC, pr.sort_order ASC
            ", $params + $prParams);

            return $this->agregaSla($rows, 'equipa', 'batch_id');
        }

        [$opFilter, $opParams] = $this->operatorClause($operatorIds, 'p.closed_by');

        $rows = $this->run("
            SELECT CONCAT(u.first_name, ' ', u.last_name) AS colaborador,
                   pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                   p.created_at, p.closed_at, p.sla_paused_total_minutes, p.sla_closed_minutes,
                   u.id AS operator_id, pr.id AS priority_id
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_user u ON u.id = p.closed_by
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}{$opFilter}{$prFilter}
            ORDER BY colaborador ASC, pr.sort_order ASC
        ", $params + $opParams + $prParams);

        return $this->agregaSla($rows, 'colaborador', 'operator_id');
    }

    /**
     * Agrega os processos por (operador|equipa × prioridade), contando os
     * minutos com a regra de SLA em vigor. Devolve as mesmas colunas que a
     * versão em SQL, para as vistas e o Excel não notarem a diferença.
     *
     * @param list<array<string,mixed>> $rows
     */
    private function agregaSla(array $rows, string $labelKey, string $idKey): array
    {
        $grupos = [];

        foreach ($rows as $row) {
            $chave = $row[$idKey] . '|' . $row['priority_id'];
            $minutos = sla_process_minutes($row);

            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [
                    $labelKey => $row[$labelKey],
                    'prioridade' => $row['prioridade'],
                    'sla_minutos' => $row['sla_minutos'],
                    'concluidos' => 0,
                    'dentro_sla' => 0,
                    'tempo_medio_min' => 0,
                    $idKey => $row[$idKey],
                    'priority_id' => $row['priority_id'],
                    '_soma' => 0,
                ];
            }

            $grupos[$chave]['concluidos']++;
            $grupos[$chave]['dentro_sla'] += self::withinSla($minutos, $row['sla_minutos']);
            $grupos[$chave]['_soma'] += $minutos;
        }

        foreach ($grupos as &$grupo) {
            $grupo['tempo_medio_min'] = (int) round($grupo['_soma'] / $grupo['concluidos']);
            unset($grupo['_soma']);
        }
        unset($grupo);

        return array_values($grupos);
    }

    /** 1 se o processo ficou dentro do SLA; 0 se estourou (ou não tem SLA definido). */
    private static function withinSla(int $minutos, mixed $slaMinutos): int
    {
        if ($slaMinutos === null || $slaMinutos === '') {
            return 0;
        }

        return $minutos <= (int) $slaMinutos ? 1 : 0;
    }

    /**
     * Drill-down do Relatório SLA: os processos CONCLUÍDOS que compõem uma
     * célula (operador OU equipa, numa prioridade). Devolve cada processo com
     * início, fim e tempo total de finalização (minutos), e se ficou dentro do
     * SLA — os mesmos critérios da agregação sla().
     *
     * @return list<array<string,mixed>>
     */
    public function slaClosedProcesses(?string $from, ?string $to, int $priorityId, ?int $operatorId, ?int $batchId): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $who = '';
        if ($operatorId !== null) {
            $who = ' AND p.closed_by = :who';
            $params['who'] = $operatorId;
        } elseif ($batchId !== null) {
            $who = ' AND p.batch_id = :who';
            $params['who'] = $batchId;
        }
        $params['pri'] = $priorityId;

        $rows = $this->run("
            SELECT p.id, p.process_number, p.created_at, p.closed_at, p.sla_paused_total_minutes, p.sla_closed_minutes,
                   c.name AS customer_name, v.plate AS vehicle_plate,
                   pr.default_sla_minutes AS sla_minutos
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL
              AND st.code IN ('SOLVED', 'CLOSED')
              AND p.priority_id = :pri {$period}{$who}
            ORDER BY p.created_at ASC
        ", $params);

        // O tempo decorrido é calculado em PHP (e não com TIMESTAMPDIFF) para
        // respeitar o horário de atendimento — ver sla_elapsed_minutes().
        // Quem pausou vem da Timeline; os minutos vêm da coluna, para a coluna
        // "Em pausa" mostrar exatamente o que foi descontado ao tempo total.
        $pausas = $this->pausasPorProcesso(array_map(static fn (array $r): int => (int) $r['id'], $rows));

        foreach ($rows as &$row) {
            $row['tempo_total_min'] = sla_process_minutes($row);
            $row['dentro_sla'] = self::withinSla($row['tempo_total_min'], $row['sla_minutos']);

            $row['pausa_min'] = (int) ($row['sla_paused_total_minutes'] ?? 0);
            $row['pausa_por'] = $pausas[(int) $row['id']]['por'] ?? '';
        }
        unset($row);

        return $rows;
    }

    /**
     * Tempo em PAUSA de cada processo e quem o pausou, reconstruído a partir
     * dos eventos da Timeline.
     *
     * Não se usa tb_process.sla_paused_minutes porque esse valor é ZERADO a
     * cada contacto (quando o SLA renova na interação), pelo que representa
     * "pausa desde o último contacto" e não o total do processo. Para explicar
     * o tempo de um processo concluído, o que interessa é o total.
     *
     * @param  int[] $processIds
     * @return array<int, array{minutos:int, por:string}>
     */
    private function pausasPorProcesso(array $processIds): array
    {
        $ids = array_values(array_unique(array_filter($processIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT e.process_id, e.event_type, e.created_at,
                   TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))) AS utilizador
            FROM tb_event e
            LEFT JOIN tb_user u ON u.id = e.user_id
            WHERE e.process_id IN ({$placeholders})
              AND e.event_type IN ('PROCESS_WAITING', 'PROCESS_RESUMED')
              AND e.deleted_at IS NULL
            ORDER BY e.process_id ASC, e.created_at ASC, e.id ASC
        ");
        $stmt->execute($ids);

        $eventosPorProcesso = [];
        $quemPausou = [];
        foreach ($stmt->fetchAll() as $evento) {
            $processId = (int) $evento['process_id'];
            $eventosPorProcesso[$processId][] = $evento;

            // Só quem põe em espera é que "pausa" — quem retoma não conta.
            $nome = trim((string) $evento['utilizador']);
            if ($evento['event_type'] === 'PROCESS_WAITING' && $nome !== '') {
                $quemPausou[$processId][$nome] = true;
            }
        }

        $resultado = [];
        foreach ($eventosPorProcesso as $processId => $eventos) {
            $resultado[$processId] = [
                'minutos' => \App\Modules\Process\Support\SlaPauseRebuilder::minutesFromEvents($eventos),
                'por' => implode(', ', array_keys($quemPausou[$processId] ?? [])),
            ];
        }

        return $resultado;
    }

    /**
     * Cumpriu o prazo, mas o processo voltou. Um processo fechado dentro do
     * SLA e reaberto pouco depois não foi resolvido — foi despachado. Desde
     * que o SLA paga prémios, isto tem de estar à vista.
     *
     * Não se filtra por uma janela de dias: mostra-se quantos dias passaram
     * até à reabertura e deixa-se quem lê decidir o que é "cedo demais". Uma
     * reabertura ao fim de um dia diz algo muito diferente de uma ao fim de
     * três semanas, e o número em bruto é mais honesto do que um corte fixo.
     */
    public function reopenedWithinSla(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT p.id, p.process_number, p.created_at, p.closed_at,
                   p.sla_paused_total_minutes, p.sla_closed_minutes, p.reopen_count,
                   pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                   c.name AS cliente,
                   CONCAT(br.name, ' · ', d.name) AS equipa,
                   TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))) AS fechado_por,
                   (SELECT MIN(e.created_at) FROM tb_event e
                     WHERE e.process_id = p.id AND e.event_type = 'PROCESS_CLOSED'
                       AND e.deleted_at IS NULL) AS primeiro_fecho,
                   (SELECT MIN(e.created_at) FROM tb_event e
                     WHERE e.process_id = p.id AND e.event_type = 'PROCESS_REOPENED'
                       AND e.deleted_at IS NULL) AS primeira_reabertura
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_batch bt ON bt.id = p.batch_id
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            LEFT JOIN tb_user u ON u.id = p.closed_by
            WHERE p.deleted_at IS NULL AND p.reopen_count > 0
              AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}
            ORDER BY p.created_at DESC
        ", $params);

        $resultado = [];
        foreach ($rows as $row) {
            // Só interessa quem cumpriu o prazo: quem já falhou está no
            // Relatório SLA, e apareceria aqui a duplicar o mesmo problema.
            $minutos = sla_process_minutes($row);
            if (self::withinSla($minutos, $row['sla_minutos']) !== 1) {
                continue;
            }

            // Dias corridos entre o primeiro fecho e a primeira reabertura,
            // lidos da Timeline e não de closed_at — ao reabrir, o closed_at
            // do fecho anterior é limpo, e só os eventos guardam essa data.
            // Tempo real e não minutos de atendimento: a pergunta é quanto
            // tempo o cliente esteve a pensar que o assunto estava resolvido.
            $dias = '—';
            if ($row['primeiro_fecho'] !== null && $row['primeira_reabertura'] !== null) {
                $delta = strtotime((string) $row['primeira_reabertura']) - strtotime((string) $row['primeiro_fecho']);
                $dias = max(0, (int) floor($delta / 86400));
            }

            $resultado[] = [
                'id' => (int) $row['id'],
                'processo' => $row['process_number'],
                'cliente' => $row['cliente'],
                'equipa' => $row['equipa'],
                'prioridade' => $row['prioridade'],
                'fechado_por' => $row['fechado_por'],
                'tempo_ate_fechar' => sla_human($minutos),
                'dias_ate_reabrir' => $dias,
                'reaberturas' => (int) $row['reopen_count'],
            ];
        }

        return $resultado;
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
