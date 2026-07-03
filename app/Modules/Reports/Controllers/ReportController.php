<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Reports\Repositories\AnalyticsRepository;
use App\Modules\Reports\Services\ExcelExportService;
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
        'sla' => ['⏱️ Relatório SLA', 'Cumprimento de SLA por prioridade e tempo médio de resolução.'],
        'operators' => ['🧑‍💼 Produtividade · Operadores', 'Processos criados, assumidos e concluídos por operador.'],
        'batches' => ['🏢 Lotes (Filial · Departamento)', 'Volume, em andamento, concluídos e reaberturas por lote.'],
        'customers' => ['👥 Clientes', 'Top clientes por nº de processos, contactos e reaberturas.'],
        'vehicles' => ['🚗 Viaturas', 'Top matrículas por nº de processos e reaberturas.'],
        'reopened' => ['🔁 Reaberturas', 'Processos reabertos pelo menos uma vez.'],
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

        [$from, $to] = $this->periodFromRequest($request);
        $repository = new AnalyticsRepository();

        $rows = match ($code) {
            'sla' => $repository->sla($from, $to),
            'operators' => $repository->operators($from, $to),
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

        $this->view('Reports/Views/report', [
            'code' => $code,
            'title' => self::REPORTS[$code][0],
            'description' => self::REPORTS[$code][1],
            'rows' => $rows,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ]);
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
