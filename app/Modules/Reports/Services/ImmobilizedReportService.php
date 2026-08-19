<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Core\Settings;
use App\Modules\Process\Support\BusinessClock;
use App\Modules\Reports\Repositories\ImmobilizedReportRepository;
use App\Modules\Reports\Repositories\ImmobilizedReportSource;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Monta o Relatório de Imobilizados: junta os processos e os seus contactos,
 * corre a ImmobilizedComplianceCalculator sobre cada um e devolve uma
 * estrutura pronta para a grelha (uma linha por processo, contactos na
 * horizontal) e para a exportação Excel (uma linha por contacto).
 */
final class ImmobilizedReportService
{
    private const DEFAULT_DEADLINE_MINUTES = 960; // 16h úteis
    private const MAX_PROCESSES = 500;            // teto de segurança, como os outros relatórios

    public function __construct(
        private readonly ImmobilizedReportSource $repository = new ImmobilizedReportRepository(),
    ) {
    }

    /**
     * @param array{from?:?string, to?:?string, plate?:string, vehicle?:string} $filters
     * @return array{processes:list<array<string,mixed>>, summary:array{processes:int,contacts:int,onTime:int,late:int,pct:?int}, maxContacts:int, truncated:bool, limit:int}
     */
    public function build(array $filters): array
    {
        $subject = trim((string) Settings::get('next_contact_subject_code', 'IMO')) ?: 'IMO';
        $processes = $this->repository->processes($subject, $filters, self::MAX_PROCESSES);
        $truncated = count($processes) >= self::MAX_PROCESSES;

        $ids = array_map(static fn (array $p): int => (int) $p['id'], $processes);
        $contactsByProcess = $this->repository->contactsByProcess($ids);

        [$between, $deadlineFrom] = self::clock();
        $nowTs = time();

        $rows = [];
        $totContacts = 0;
        $totOnTime = 0;
        $totLate = 0;
        $maxContacts = 0;

        foreach ($processes as $p) {
            $pid = (int) $p['id'];
            $deadline = (int) ($p['deadline_minutes'] ?? self::DEFAULT_DEADLINE_MINUTES);
            $closed = in_array((string) $p['status_code'], ['SOLVED', 'CLOSED'], true);

            $raw = $contactsByProcess[$pid] ?? [];
            $contactTs = array_map(static fn (array $c): int => self::ts($c['ts']), $raw);

            $calc = new ImmobilizedComplianceCalculator($between, $deadlineFrom, $deadline);
            $eval = $calc->evaluate(self::ts((string) $p['created_at']), $contactTs, $nowTs, $closed);

            // Cola o resultado do cálculo a cada contacto (para mostrar o texto).
            $contacts = [];
            foreach ($eval['contacts'] as $i => $c) {
                $meta = $raw[$i];
                $contacts[] = [
                    'when' => self::fmt($c['ts']),
                    'channel' => $meta['channel'],
                    'who' => $meta['who'],
                    'text' => $meta['text'],
                    'businessMinutes' => $c['businessMinutes'],
                    'humanGap' => self::humanHours($c['businessMinutes']),
                    'onTime' => $c['onTime'],
                    'overBy' => $c['overBy'],
                    'humanOver' => self::humanHours($c['overBy']),
                ];
            }

            $next = null;
            if ($eval['next'] !== null) {
                $n = $eval['next'];
                $next = [
                    'due' => self::fmt($n['dueTs']),
                    'overdue' => $n['overdue'],
                    'humanOver' => self::humanHours($n['overBy']),
                    'humanRemaining' => self::humanHours($n['remaining']),
                ];
            }

            $count = count($contacts);
            $maxContacts = max($maxContacts, $count);
            $totContacts += $count;
            $totOnTime += $eval['onTimeCount'];
            $totLate += $eval['lateCount'];

            $rows[] = [
                'id' => $pid,
                'process_number' => (string) $p['process_number'],
                'status_name' => (string) $p['status_name'],
                'closed' => $closed,
                'customer_name' => (string) $p['customer_name'],
                'plate' => (string) $p['vehicle_plate'],
                'vehicle' => trim((string) ($p['vehicle_brand'] ?? '') . ' ' . (string) ($p['vehicle_model'] ?? '')),
                'priority_name' => (string) $p['priority_name'],
                'priority_color' => (string) ($p['priority_color'] ?? '#6b7280'),
                'responsible' => trim((string) ($p['resp_first'] ?? '') . ' ' . (string) ($p['resp_last'] ?? '')) ?: '—',
                'branch' => trim((string) ($p['branch_name'] ?? '') . ' · ' . (string) ($p['department_name'] ?? ''), ' ·'),
                'start' => self::fmt(self::ts((string) $p['created_at'])),
                'deadlineMinutes' => $deadline,
                'deadlineHuman' => self::humanHours($deadline),
                'contacts' => $contacts,
                'next' => $next,
                'onTime' => $eval['onTimeCount'],
                'late' => $eval['lateCount'],
                'pct' => $count > 0 ? (int) round($eval['onTimeCount'] / $count * 100) : null,
            ];
        }

        return [
            'processes' => $rows,
            'summary' => [
                'processes' => count($rows),
                'contacts' => $totContacts,
                'onTime' => $totOnTime,
                'late' => $totLate,
                'pct' => $totContacts > 0 ? (int) round($totOnTime / $totContacts * 100) : null,
            ],
            'maxContacts' => $maxContacts,
            'truncated' => $truncated,
            'limit' => self::MAX_PROCESSES,
        ];
    }

    /**
     * Uma linha por contacto, para o Excel poder filtrar/ordenar/pivotar.
     *
     * @param array{processes:list<array<string,mixed>>} $report resultado de build()
     * @return list<array<string,string>>
     */
    public function excelRows(array $report): array
    {
        $out = [];
        foreach ($report['processes'] as $p) {
            if ($p['contacts'] === []) {
                $out[] = self::excelBase($p) + [
                    'Nº Contacto' => '—',
                    'Data Contacto' => '(sem contactos)',
                    'Canal' => '',
                    'Operador' => '',
                    'Horas úteis desde anterior' => '',
                    'Estado' => $p['next']['overdue'] ?? false ? 'EM ATRASO' : 'A aguardar 1º contacto',
                    'O que foi falado' => '',
                ];
                continue;
            }
            foreach ($p['contacts'] as $i => $c) {
                $out[] = self::excelBase($p) + [
                    'Nº Contacto' => (string) ($i + 1),
                    'Data Contacto' => $c['when'],
                    'Canal' => $c['channel'],
                    'Operador' => $c['who'],
                    'Horas úteis desde anterior' => $c['humanGap'],
                    'Estado' => $c['onTime'] ? 'No prazo' : ('EM ATRASO (+' . $c['humanOver'] . ')'),
                    'O que foi falado' => $c['text'],
                ];
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $p @return array<string,string> */
    private static function excelBase(array $p): array
    {
        return [
            'Processo' => (string) $p['process_number'],
            'Matrícula' => (string) $p['plate'],
            'Viatura' => (string) $p['vehicle'],
            'Cliente' => (string) $p['customer_name'],
            'Início' => (string) $p['start'],
            'Cumprimento' => $p['pct'] === null ? '—' : $p['pct'] . '%',
        ];
    }

    /**
     * Relógio a usar: com o horário de atendimento ligado (mesmo do SLA), conta
     * minutos úteis e salta fins de semana/feriados; desligado, minutos corridos.
     *
     * @return array{0:callable(int,int):int, 1:callable(int,int):int}
     */
    private static function clock(): array
    {
        if (BusinessClock::enabled()) {
            return [
                static fn (int $a, int $b): int => BusinessClock::minutesBetween($a, $b),
                static fn (int $a, int $m): int => BusinessClock::deadlineFrom($a, $m),
            ];
        }

        return [
            static fn (int $a, int $b): int => (int) max(0, ($b - $a) / 60),
            static fn (int $a, int $m): int => $a + $m * 60,
        ];
    }

    private static function ts(string $utc): int
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
    }

    private static function fmt(int $ts): string
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone(app_timezone())->format('d/m/Y H:i');
    }

    /** Minutos → "16h", "1h30", "45min". */
    private static function humanHours(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m . 'min';
        }

        return $m === 0 ? $h . 'h' : $h . 'h' . sprintf('%02d', $m);
    }
}
