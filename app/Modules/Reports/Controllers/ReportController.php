<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Reports\Repositories\AnalyticsRepository;
use App\Modules\Reports\Services\ExcelExportService;
use App\Modules\Reports\Services\ImmobilizedReportService;
use App\Modules\Reports\Services\ReportService;
use App\Modules\Reports\Services\SimplePdfWriter;
use App\Traits\AuditTrait;

/**
 * RF-0041 a RF-0043 - Exportação (Excel/PDF/CSV) / RF-0027 - toda exportação gera Auditoria.
 */
final class ReportController extends Controller
{
    use AuditTrait;

    /** Relatórios disponíveis (código => [título, descrição]). */
    private const REPORTS = [
        'sla' => ['⏱️ Relatório SLA', 'Cumprimento de SLA por colaborador e prioridade — quem está a cumprir e quem não.'],
        'sla_reopened' => ['↩️ Cumpriu, mas voltou', 'Processos fechados dentro do SLA que foram reabertos — o contrapeso ao prémio.'],
        'sla_breakdown' => ['⏳ Onde se perde o tempo', 'Fila, trabalho, pausa e tempo encerrado — por equipa, em média por processo.'],
        'sla_pickup' => ['🕐 Tempo até alguém pegar', 'Quanto tempo os processos esperam na fila, por equipa e hora de entrada.'],
        'sla_subject' => ['🎯 Prazos por assunto', 'Cumprimento por assunto — mostra que prazos estão mal calibrados.'],
        'sla_load' => ['📈 Carga contra capacidade', 'Entradas e incumprimentos por dia da semana e hora — onde falta gente.'],
        'operators' => ['🧑‍💼 Produtividade · Operadores', 'Processos criados, assumidos e concluídos por operador.'],
        'batches' => ['🏢 Lotes (Filial · Departamento)', 'Volume, em andamento, concluídos e reaberturas por lote.'],
        'customers' => ['👥 Clientes', 'Top clientes por nº de processos, contactos e reaberturas.'],
        'vehicles' => ['🚗 Viaturas', 'Top matrículas por nº de processos e reaberturas.'],
        'reopened' => ['🔁 Reaberturas', 'Processos reabertos pelo menos uma vez.'],
    ];

    /**
     * Explicação de cada relatório, mostrada no "ℹ️" por cima da tabela.
     *
     * Um relatório que não se percebe não sustenta decisão nenhuma — e estes
     * decidem prémios. Cada um responde a três coisas: o que está a ver, que
     * decisão ajuda a tomar, e como se lê (que é onde moram os mal-entendidos,
     * como a diferença entre média e mediana).
     *
     * @var array<string, array{o_que:string, decisao:string, como_ler:string}>
     */
    private const REPORT_HELP = [
        'sla' => [
            'o_que' => 'Percentagem de processos concluídos dentro do prazo, por colaborador (ou equipa) e prioridade.',
            'decisao' => 'Quem precisa de apoio e quem está a cumprir. É a base do prémio por SLA.',
            'como_ler' => 'O tempo conta apenas o que o processo esteve a ser trabalhado: fora do horário de atendimento, em pausa e encerrado entre reaberturas não contam. Compare sempre as duas colunas de tempo: o MEDIANO é o caso típico (metade demorou menos), e o MÉDIO sobe com um único processo esquecido. Quando o médio é muito maior que o mediano, o trabalho corre bem e há casos pontuais a investigar — não é a pessoa que é lenta. A percentagem de cumprimento é contada processo a processo e não sai destas médias. Clique em "Ver processos" para ver caso a caso.',
        ],
        'sla_reopened' => [
            'o_que' => 'Processos que foram fechados DENTRO do prazo e, mesmo assim, tiveram de ser reabertos.',
            'decisao' => 'Se estes casos forem muitos, o prémio está a recompensar rapidez a fingir — e a regra deve passar a exigir prazo cumprido E sem reabertura.',
            'como_ler' => '"Dias até reabrir" é tempo real de calendário: quanto tempo o cliente esteve a pensar que o assunto estava resolvido. Poucos dias é mau sinal; três semanas depois pode ser um assunto novo.',
        ],
        'sla_breakdown' => [
            'o_que' => 'O ciclo de vida de um processo dividido em quatro parcelas, em média por processo: à espera na fila, a ser trabalhado, em pausa, e encerrado entre reaberturas.',
            'decisao' => 'Diz em que frente atacar. Fila alta é dimensionamento ou distribuição; pausa alta é o processo com clientes e fornecedores; trabalho alto é formação ou ferramentas.',
            'como_ler' => 'As percentagens são a fatia de cada parcela no tempo total dessa equipa — somam 100%. Só entram processos concluídos.',
        ],
        'sla_pickup' => [
            'o_que' => 'Quanto tempo os processos ficam à espera na fila antes de alguém os assumir, agrupados pela hora a que entraram.',
            'decisao' => 'Escalas, turnos e reforço. É a única parcela do SLA que não depende de clientes nem de fornecedores — logo, a mais fácil de corrigir.',
            'como_ler' => 'A MEDIANA é o caso típico: metade dos processos esperou menos do que isso. A MÉDIA é puxada para cima por poucos casos extremos. Quando a média é muito maior que a mediana, o problema não é a fila estar lenta — são alguns processos esquecidos, que o "pior caso" identifica.',
        ],
        'sla_subject' => [
            'o_que' => 'Cumprimento do prazo por assunto, em vez de por pessoa.',
            'decisao' => 'Recalibrar os prazos. Um agendamento e uma peritagem não deviam ter o mesmo relógio.',
            'como_ler' => 'Quando um assunto falha em vários operadores diferentes, o problema é o prazo e não a equipa — a coluna "veredicto" assinala esses casos. Compare o TEMPO MEDIANO (o caso típico: metade demorou menos) com os minutos de SLA: se a mediana já ultrapassa o prazo, nem o caso normal cumpre e é o prazo que tem de mudar. Se a mediana cumpre mas a MÉDIA é muito maior, o prazo está bem — o que há são casos concretos a descarrilar, e são esses que se investigam. O "% dentro SLA" é contado processo a processo, nunca a partir destas médias.',
        ],
        'sla_load' => [
            'o_que' => 'Quantos processos entram em cada dia da semana e hora, e quantos desses acabaram por falhar o prazo.',
            'decisao' => 'Se o incumprimento acompanha o volume, falta gente nessas horas. Se não acompanha, o problema é outro — e evita-se contratar sem necessidade.',
            'como_ler' => 'A hora é a de ENTRADA do processo, não a da conclusão. "% fora" é sobre os já concluídos, por isso as horas mais recentes podem ainda estar incompletas.',
        ],
        'operators' => [
            'o_que' => 'Volume de trabalho por operador: criados, assumidos e concluídos.',
            'decisao' => 'Distribuição de carga entre a equipa.',
            'como_ler' => 'Mede volume, não qualidade nem cumprimento de prazo. Para isso, veja o Relatório SLA.',
        ],
        'batches' => [
            'o_que' => 'Volume, processos em andamento, concluídos e reaberturas por Filial · Departamento.',
            'decisao' => 'Onde está a carga da operação e que departamentos acumulam reaberturas.',
            'como_ler' => 'Muitas reaberturas num departamento costumam apontar para processos fechados cedo demais.',
        ],
        'customers' => [
            'o_que' => 'Clientes com mais processos, contactos e reaberturas.',
            'decisao' => 'Quem merece acompanhamento próximo e que contas dão mais trabalho.',
            'como_ler' => 'Muitos contactos no mesmo cliente pode ser dificuldade em contactá-lo, e não excesso de zelo.',
        ],
        'vehicles' => [
            'o_que' => 'Matrículas com mais processos e reaberturas.',
            'decisao' => 'Viaturas problemáticas, que voltam à oficina pelo mesmo motivo.',
            'como_ler' => 'Repetições na mesma matrícula podem indicar uma reparação que não resolveu.',
        ],
        'reopened' => [
            'o_que' => 'Todos os processos reabertos pelo menos uma vez.',
            'decisao' => 'Qualidade do fecho. Um processo reaberto é trabalho feito duas vezes.',
            'como_ler' => 'Para ver só os que tinham cumprido o prazo — os que mais pesam no prémio — use "Cumpriu, mas voltou".',
        ],
    ];

    public function index(Request $request): never
    {
        $this->view('Reports/Views/index', [
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'reports' => self::REPORTS,
        ]);
    }

    /** Página genérica de relatório tabular: /reports/view/{code}. */
    public function show(Request $request, array $params): never
    {
        $code = (string) $params['code'];

        if (!isset(self::REPORTS[$code])) {
            http_response_code(404);
            echo 'Relatório não encontrado.';
            exit;
        }

        $rows = $this->buildReportRows($code, $request);
        $hasOperatorFilter = in_array($code, ['sla', 'operators'], true);
        $operatorIds = array_map('intval', (array) $request->input('operators', []));
        $groupBy = $this->slaGroupBy($request);

        $this->view('Reports/Views/report', [
            'code' => $code,
            'title' => self::REPORTS[$code][0],
            'description' => self::REPORTS[$code][1],
            'rows' => $rows,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            // O filtro de operador só faz sentido a agrupar por colaborador.
            'operatorOptions' => $hasOperatorFilter && $groupBy !== 'equipa' ? (new \App\Modules\Auth\Repositories\UserRepository())->listAll() : [],
            'selectedOperators' => $operatorIds,
            // SLA ganha filtro por prioridade e alternância colaborador/equipa.
            'priorityOptions' => $code === 'sla' ? (new \App\Modules\Process\Repositories\PriorityRepository())->listAll() : [],
            'selectedPriorities' => array_map('intval', (array) $request->input('priorities', [])),
            'showGroupToggle' => $code === 'sla',
            'groupBy' => $groupBy,
            'help' => self::REPORT_HELP[$code] ?? null,
        ]);
    }

    /** SLA: modo de saída — por colaborador (omissão) ou por equipa. */
    private function slaGroupBy(Request $request): string
    {
        return $request->input('group') === 'equipa' ? 'equipa' : 'colaborador';
    }

    /**
     * Drill-down do Relatório SLA: fragmento (para modal) com os processos
     * concluídos de uma célula (operador/equipa × prioridade), tempo total de
     * cada um e, no fim, a média e a % dentro do SLA.
     */
    public function slaProcesses(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);
        $priorityId = (int) $request->input('priority_id', 0);
        $operatorId = (int) $request->input('operator_id', 0);
        $batchId = (int) $request->input('batch_id', 0);

        $rows = (new AnalyticsRepository())->slaClosedProcesses(
            $from,
            $to,
            $priorityId,
            $operatorId > 0 ? $operatorId : null,
            $batchId > 0 ? $batchId : null
        );

        $count = count($rows);
        $within = 0;
        $sum = 0;
        foreach ($rows as $r) {
            $within += (int) $r['dentro_sla'];
            $sum += (int) $r['tempo_total_min'];
        }

        $this->view('Reports/Views/sla_processes_modal', [
            'rows' => $rows,
            'label' => trim((string) $request->input('label', '')),
            'priorityName' => trim((string) $request->input('priority', '')),
            'count' => $count,
            'within' => $within,
            'avg' => $count > 0 ? (int) round($sum / $count) : 0,
            // A mediana ao lado da média, para se ver se o tempo alto é a
            // regra ou obra de um ou dois processos.
            'mediana' => AnalyticsRepository::medianaDe(array_map(
                static fn (array $r): int => (int) $r['tempo_total_min'],
                $rows
            )),
            'pct' => $count > 0 ? (int) round($within / $count * 100) : null,
        ]);
    }

    /**
     * Drill-down do "Tempo até alguém pegar": abre os processos de uma equipa
     * numa hora, ordenados do mais rápido para o mais lento.
     *
     * É onde se vê o que a média esconde — a fila corre estável e depois um
     * punhado de processos dispara. São esses que há a investigar.
     */
    public function pickupProcesses(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);
        $batchId = (int) $request->input('batch_id', 0);
        $hora = str_pad((string) (int) $request->input('hora', 0), 2, '0', STR_PAD_LEFT);

        $dados = (new AnalyticsRepository())->pickupProcesses($batchId, $hora, $from, $to);
        $processos = $dados['processos'];
        $tempos = array_column($processos, 'minutos');

        $this->view('Reports/Views/pickup_processes_modal', [
            'processos' => $processos,
            'limites' => $dados['limites'],
            'mediana' => $dados['mediana'],
            'label' => trim((string) $request->input('label', '')),
            'hora' => $hora,
            'count' => count($processos),
            'media' => $tempos !== [] ? (int) round(array_sum($tempos) / count($tempos)) : 0,
            'anomalos' => count(array_filter($processos, static fn (array $p): bool => $p['classe'] !== 'normal')),
        ]);
    }

    /** Constrói as linhas de um relatório tabular (partilhado pela vista e pelo Excel). */
    private function buildReportRows(string $code, Request $request): array
    {
        [$from, $to] = $this->periodFromRequest($request);
        $repository = new AnalyticsRepository();

        // Filtro por operador(es) — só nos relatórios SLA e Produtividade.
        $operatorIds = array_map('intval', (array) $request->input('operators', []));
        // SLA: filtro por prioridade e modo de agrupamento (colaborador/equipa).
        $priorityIds = array_map('intval', (array) $request->input('priorities', []));
        $groupBy = $this->slaGroupBy($request);

        $rows = match ($code) {
            'sla' => $repository->sla($from, $to, $operatorIds, $priorityIds, $groupBy),
            'sla_reopened' => $repository->reopenedWithinSla($from, $to),
            'sla_breakdown' => $repository->timeBreakdown($from, $to),
            'sla_pickup' => $repository->timeToAssume($from, $to),
            'sla_subject' => $repository->slaBySubject($from, $to),
            'sla_load' => $repository->loadVersusFailures($from, $to),
            'operators' => $repository->operators($from, $to, $operatorIds),
            'batches' => $repository->batches($from, $to),
            'customers' => $repository->customers($from, $to),
            'vehicles' => $repository->vehicles($from, $to),
            'reopened' => $repository->reopened($from, $to),
        };

        // SLA: acrescenta a % calculada de forma legível
        if ($code === 'sla') {
            foreach ($rows as &$row) {
                $row['pct_dentro_sla'] = ((int) $row['concluidos']) > 0
                    ? round(((int) $row['dentro_sla'] / (int) $row['concluidos']) * 100) . '%'
                    : '—';
            }
            unset($row);
        }

        return $rows;
    }

    /** RF-0041 - descarrega qualquer relatório tabular em Excel, com os filtros atuais. */
    public function exportReportXls(Request $request, array $params): never
    {
        $code = (string) $params['code'];

        if (!isset(self::REPORTS[$code])) {
            http_response_code(404);
            echo 'Relatório não encontrado.';
            exit;
        }

        $rows = $this->buildReportRows($code, $request);
        [$from, $to] = $this->periodFromRequest($request);

        $this->logAudit('EXPORTED', 'report_' . $code, 0, null, ['from' => $from, 'to' => $to, 'rows' => count($rows), 'format' => 'xls']);

        // O id interno não interessa na folha de cálculo.
        $rows = array_map(static function (array $row): array {
            unset($row['id']);

            return $row;
        }, $rows);

        $html = (new ExcelExportService())->render($rows, self::REPORTS[$code][0]);

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_' . $code . '_' . date('Y-m-d_His') . '.xls"');
        echo $html;
        exit;
    }

    /** Relatório de Imobilizados — cumprimento do prazo de contacto (16h úteis). */
    public function immobilized(Request $request): never
    {
        $report = (new ImmobilizedReportService())->build($this->immobilizedFilters($request));

        $this->view('Reports/Views/immobilized', [
            'report' => $report,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'plate' => (string) $request->input('plate', ''),
            'vehicle' => (string) $request->input('vehicle', ''),
            'operatorOptions' => (new \App\Modules\Auth\Repositories\UserRepository())->listAll(),
            'selectedOperator' => (int) $request->input('operator_id', 0),
            'selectedState' => (string) $request->input('state', ''),
        ]);
    }

    /** Excel do Relatório de Imobilizados: uma linha por contacto, com os filtros atuais. */
    public function exportImmobilizedXls(Request $request): never
    {
        $service = new ImmobilizedReportService();
        $report = $service->build($this->immobilizedFilters($request));
        $rows = $service->excelRows($report);

        $this->logAudit('EXPORTED', 'report_imobilizados', 0, null, [
            'rows' => count($rows),
            'processes' => $report['summary']['processes'],
            'format' => 'xls',
        ]);

        $html = (new ExcelExportService())->render($rows, 'Imobilizados - Cumprimento de Prazos');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_imobilizados_' . date('Y-m-d_His') . '.xls"');
        echo $html;
        exit;
    }

    /** @return array{from:?string, to:?string, plate:string, vehicle:string, operator_id:int, state:string} */
    private function immobilizedFilters(Request $request): array
    {
        [$from, $to] = $this->periodFromRequest($request);
        $state = (string) $request->input('state', '');

        return [
            'from' => $from,
            'to' => $to,
            'plate' => trim((string) $request->input('plate', '')),
            'vehicle' => trim((string) $request->input('vehicle', '')),
            'operator_id' => (int) $request->input('operator_id', 0),
            'state' => in_array($state, ['abertos', 'fechados'], true) ? $state : '',
        ];
    }

    /** Heatmap de Contactos: interações por dia da semana × hora. */
    public function heatmap(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);

        $grid = array_fill(1, 7, array_fill(0, 24, 0));
        $max = 0;

        foreach ((new AnalyticsRepository())->contactHeatmap($from, $to) as $cell) {
            $grid[(int) $cell['weekday']][(int) $cell['hour']] = (int) $cell['total'];
            $max = max($max, (int) $cell['total']);
        }

        $this->view('Reports/Views/heatmap', [
            'grid' => $grid,
            'max' => $max,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ]);
    }

    /** Excel do Heatmap: grelha dia da semana × hora (0-23) com totais. */
    public function exportHeatmapXls(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);

        $grid = array_fill(1, 7, array_fill(0, 24, 0));
        foreach ((new AnalyticsRepository())->contactHeatmap($from, $to) as $cell) {
            $grid[(int) $cell['weekday']][(int) $cell['hour']] = (int) $cell['total'];
        }

        $dayNames = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

        $rows = [];
        foreach ($grid as $weekday => $hours) {
            $row = ['dia' => $dayNames[$weekday]];
            foreach ($hours as $hour => $total) {
                $row[sprintf('%02dh', $hour)] = $total;
            }
            $rows[] = $row;
        }

        $this->logAudit('EXPORTED', 'report_heatmap', 0, null, ['from' => $from, 'to' => $to, 'format' => 'xls']);

        $html = (new ExcelExportService())->render($rows, 'Heatmap de Contactos');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_heatmap_' . date('Y-m-d_His') . '.xls"');
        echo $html;
        exit;
    }

    private function periodFromRequest(Request $request): array
    {
        return [
            $request->input('from') !== '' ? (string) $request->input('from') : null,
            $request->input('to') !== '' ? (string) $request->input('to') : null,
        ];
    }

    public function exportProcessesCsv(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);

        $rows = (new ReportService())->processesBetween($from, $to);

        $this->logAudit('EXPORTED', 'tb_process', 0, null, ['from' => $from, 'to' => $to, 'rows' => count($rows)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="processos_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // BOM para Excel abrir UTF-8 corretamente

        if ($rows === []) {
            fclose($output);
            exit;
        }

        fputcsv($output, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    public function exportProcessesXls(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);

        $rows = (new ReportService())->processesBetween($from, $to);

        $this->logAudit('EXPORTED', 'tb_process', 0, null, ['from' => $from, 'to' => $to, 'rows' => count($rows), 'format' => 'xls']);

        $html = (new ExcelExportService())->render($rows, 'Processos');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="processos_' . date('Y-m-d_His') . '.xls"');
        echo $html;
        exit;
    }

    public function exportProcessesPdf(Request $request): never
    {
        [$from, $to] = $this->periodFromRequest($request);

        $rows = (new ReportService())->processesBetween($from, $to);

        $this->logAudit('EXPORTED', 'tb_process', 0, null, ['from' => $from, 'to' => $to, 'rows' => count($rows), 'format' => 'pdf']);

        $writer = new SimplePdfWriter('Relatorio de Processos - OPS - ' . date('Y-m-d H:i'));

        $columns = [
            'process_number' => 16, 'customer_name' => 24, 'vehicle_plate' => 10,
            'subject_name' => 16, 'status_name' => 16, 'priority_name' => 10, 'created_at' => 20,
        ];

        $header = '';
        foreach ($columns as $field => $width) {
            $header .= str_pad(strtoupper($field), $width);
        }
        $writer->addLine($header);
        $writer->addLine(str_repeat('-', array_sum($columns)));

        foreach ($rows as $row) {
            $line = '';
            foreach ($columns as $field => $width) {
                $line .= str_pad(mb_substr((string) ($row[$field] ?? ''), 0, $width - 1), $width);
            }
            $writer->addLine($line);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="processos_' . date('Y-m-d_His') . '.pdf"');
        echo $writer->output();
        exit;
    }
}
