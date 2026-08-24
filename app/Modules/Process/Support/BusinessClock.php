<?php

declare(strict_types=1);

namespace App\Modules\Process\Support;

use App\Core\Database;
use App\Core\Settings;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Relógio de negócio para o SLA: conta apenas os minutos dentro do horário
 * de atendimento e salta os feriados. Tudo trabalhado na hora local
 * (Europe/Lisbon, via app_timezone()), porque o horário — "09:00 às 18:00" —
 * é sempre pensado em hora de parede, não em UTC.
 *
 * Só é usado quando sla_business_hours_enabled = 1; caso contrário o SLA
 * continua a contar 24h/dia como antes (sla_state() decide).
 */
final class BusinessClock
{
    /** @var array<int, array{open:?string, close:?string, lunch_start:?string, lunch_end:?string}>|null */
    private static ?array $hours = null;
    /** @var array<string, bool>|null feriados: 'm-d' recorrentes + 'Y-m-d' fixos */
    private static ?array $holidays = null;

    public static function enabled(): bool
    {
        return (string) Settings::get('sla_business_hours_enabled', '0') === '1';
    }

    /**
     * Minutos ÚTEIS decorridos entre dois instantes (timestamps UTC), ou seja,
     * apenas o tempo que caiu dentro do horário de atendimento e fora de
     * feriados. Nunca negativo.
     */
    public static function minutesBetween(int $fromTs, int $toTs): int
    {
        if ($toTs <= $fromTs) {
            return 0;
        }

        $tz = app_timezone();
        $cursor = (new DateTimeImmutable('@' . $fromTs))->setTimezone($tz);
        $end = (new DateTimeImmutable('@' . $toTs))->setTimezone($tz);
        $minutes = 0;

        // Avança dia a dia, somando a interseção de [from, to] com cada
        // SEGMENTO de atendimento do dia (a manhã e a tarde, saltando o almoço).
        // Limite de segurança de ~2 anos.
        for ($i = 0; $i < 800 && $cursor < $end; $i++) {
            foreach (self::segmentsFor($cursor) as [$s, $e]) {
                $segStart = max($fromTs, $s);
                $segEnd = min($toTs, $e);
                if ($segEnd > $segStart) {
                    $minutes += (int) floor(($segEnd - $segStart) / 60);
                }
            }

            // Salta para as 00:00 do dia seguinte.
            $cursor = $cursor->modify('tomorrow midnight');
        }

        return $minutes;
    }

    /**
     * Instante (timestamp UTC) em que se atingem $slaMinutes ÚTEIS a partir de
     * $fromTs. É o "prazo" do SLA em horário de negócio.
     */
    public static function deadlineFrom(int $fromTs, int $slaMinutes): int
    {
        $tz = app_timezone();
        $cursor = (new DateTimeImmutable('@' . $fromTs))->setTimezone($tz);
        $remaining = max(0, $slaMinutes);

        for ($i = 0; $i < 800; $i++) {
            foreach (self::segmentsFor($cursor) as [$s, $e]) {
                $segStart = max($fromTs, $s);
                if ($e > $segStart) {
                    $disponivel = (int) floor(($e - $segStart) / 60);
                    if ($remaining <= $disponivel) {
                        return $segStart + $remaining * 60;
                    }
                    $remaining -= $disponivel;
                }
            }

            $cursor = $cursor->modify('tomorrow midnight');
        }

        // Salvaguarda: horário mal configurado (todos os dias fechados).
        return $fromTs + $slaMinutes * 60;
    }

    /** É um dia de atendimento? (não é fim de semana fechado nem feriado). */
    public static function isOpenDay(DateTimeImmutable $day): bool
    {
        return self::segmentsFor($day) !== [];
    }

    /**
     * Instante (timestamp UTC) $days DIAS ÚTEIS depois de $fromTs, mantendo a
     * MESMA hora do dia e saltando fins de semana e feriados. Ex.: 6ª-feira
     * 16:00 + 2 dias úteis = 3ª-feira seguinte 16:00 (salta sábado e domingo).
     */
    public static function plusBusinessDays(int $fromTs, int $days): int
    {
        if ($days <= 0) {
            return $fromTs;
        }

        $cursor = (new DateTimeImmutable('@' . $fromTs))->setTimezone(app_timezone());
        $counted = 0;

        for ($i = 0; $i < 400 && $counted < $days; $i++) {
            $cursor = $cursor->modify('+1 day'); // mantém a hora do dia
            if (self::isOpenDay($cursor)) {
                $counted++;
            }
        }

        return $cursor->getTimestamp();
    }

    /**
     * Segmentos de atendimento (timestamps UTC) do dia de $day: normalmente um
     * só [abertura, fecho], ou DOIS quando há almoço configurado — manhã
     * [abertura, início_almoço] e tarde [fim_almoço, fecho]. Vazio se for fim
     * de semana fechado ou feriado.
     *
     * @return list<array{0:int, 1:int}>
     */
    private static function segmentsFor(DateTimeImmutable $day): array
    {
        if (self::isHoliday($day)) {
            return [];
        }

        $hours = self::hours();
        $wd = (int) $day->format('w'); // 0=Dom ... 6=Sáb
        $row = $hours[$wd] ?? null;

        if ($row === null || $row['open'] === null || $row['close'] === null) {
            return [];
        }

        $mk = static fn (string $time): int|false => ($dt = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $day->format('Y-m-d') . ' ' . $time,
            $day->getTimezone()
        )) === false ? false : $dt->getTimestamp();

        $openTs = $mk($row['open']);
        $closeTs = $mk($row['close']);
        if ($openTs === false || $closeTs === false || $closeTs <= $openTs) {
            return [];
        }

        // Almoço, se configurado e válido (dentro da janela do dia).
        if (($row['lunch_start'] ?? null) !== null && ($row['lunch_end'] ?? null) !== null) {
            $lsTs = $mk($row['lunch_start']);
            $leTs = $mk($row['lunch_end']);
            if ($lsTs !== false && $leTs !== false && $leTs > $lsTs && $lsTs >= $openTs && $leTs <= $closeTs) {
                $segments = [];
                if ($lsTs > $openTs) {
                    $segments[] = [$openTs, $lsTs];
                }
                if ($closeTs > $leTs) {
                    $segments[] = [$leTs, $closeTs];
                }

                return $segments;
            }
        }

        return [[$openTs, $closeTs]];
    }

    private static function isHoliday(DateTimeImmutable $day): bool
    {
        $holidays = self::holidays();

        return isset($holidays[$day->format('m-d')]) || isset($holidays[$day->format('Y-m-d')]);
    }

    /** @return array<int, array{open:?string, close:?string, lunch_start:?string, lunch_end:?string}> */
    private static function hours(): array
    {
        if (self::$hours === null) {
            self::$hours = [];
            // Resiliente à migração 033: se as colunas de almoço ainda não
            // existirem, o horário funciona na mesma (sem almoço).
            $hasLunch = Database::hasColumn('tb_business_hours', 'lunch_start');
            $cols = $hasLunch
                ? 'weekday, open_time, close_time, lunch_start, lunch_end'
                : 'weekday, open_time, close_time';
            $rows = Database::connection()->query("SELECT {$cols} FROM tb_business_hours")->fetchAll();
            foreach ($rows as $row) {
                self::$hours[(int) $row['weekday']] = [
                    'open' => $row['open_time'],
                    'close' => $row['close_time'],
                    'lunch_start' => $hasLunch ? ($row['lunch_start'] ?? null) : null,
                    'lunch_end' => $hasLunch ? ($row['lunch_end'] ?? null) : null,
                ];
            }
        }

        return self::$hours;
    }

    /** @return array<string, bool> */
    private static function holidays(): array
    {
        if (self::$holidays === null) {
            self::$holidays = [];
            $rows = Database::connection()->query("
                SELECT holiday_date, recurring FROM tb_holiday
                WHERE active = 1 AND deleted_at IS NULL
            ")->fetchAll();
            foreach ($rows as $row) {
                $date = (string) $row['holiday_date'];
                // Recorrente → guarda 'm-d' (compara todos os anos); fixo → 'Y-m-d'.
                $key = (int) $row['recurring'] === 1 ? substr($date, 5) : $date;
                self::$holidays[$key] = true;
            }
        }

        return self::$holidays;
    }
}
