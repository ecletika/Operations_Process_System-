<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

/**
 * Avalia o cumprimento do prazo de contacto de um processo Imobilizado.
 *
 * Regra (RN-Imobilizados): o cliente tem de ser contactado no máximo a cada
 * 16 horas úteis (2 dias de atendimento). O prazo REINICIA a cada contacto —
 * cada contacto "zera" o relógio. O primeiro contacto conta desde o início
 * do processo.
 *
 * A contagem de tempo (horas úteis vs. corridas, feriados, fins de semana)
 * é injetada como duas funções, para esta classe ser pura e testável sem
 * base de dados. Em produção ligam-se ao BusinessClock (o mesmo relógio do
 * SLA); nos testes usa-se um relógio linear determinístico.
 */
final class ImmobilizedComplianceCalculator
{
    /**
     * @param callable(int,int):int $businessMinutesBetween minutos (úteis) entre dois instantes UTC
     * @param callable(int,int):int $deadlineFrom            instante UTC em que se atingem N minutos (úteis) a partir de outro
     * @param int                   $deadlineMinutes         limite entre contactos (ex.: 960 = 16h úteis)
     */
    public function __construct(
        private readonly mixed $businessMinutesBetween,
        private readonly mixed $deadlineFrom,
        private readonly int $deadlineMinutes,
    ) {
    }

    /**
     * @param int        $startTs   início do processo (epoch UTC)
     * @param list<int>  $contactTs contactos por ordem cronológica ascendente (epoch UTC)
     * @param int        $nowTs     "agora" (epoch UTC)
     * @param bool       $closed    processo já concluído/fechado (sem próximo contacto previsto)
     *
     * @return array{
     *   contacts: list<array{ts:int, businessMinutes:int, onTime:bool, overBy:int}>,
     *   next: null|array{dueTs:int, elapsed:int, overdue:bool, overBy:int, remaining:int},
     *   onTimeCount:int, lateCount:int
     * }
     */
    public function evaluate(int $startTs, array $contactTs, int $nowTs, bool $closed): array
    {
        $between = $this->businessMinutesBetween;
        $deadline = $this->deadlineFrom;

        $contacts = [];
        $onTime = 0;
        $late = 0;
        $prev = $startTs;

        foreach ($contactTs as $ts) {
            $minutes = (int) $between($prev, $ts);
            $isOnTime = $minutes <= $this->deadlineMinutes;

            $contacts[] = [
                'ts' => $ts,
                'businessMinutes' => $minutes,
                'onTime' => $isOnTime,
                'overBy' => max(0, $minutes - $this->deadlineMinutes),
            ];

            $isOnTime ? $onTime++ : $late++;
            $prev = $ts;
        }

        $next = null;
        if (!$closed) {
            $elapsed = (int) $between($prev, $nowTs);
            $next = [
                'dueTs' => (int) $deadline($prev, $this->deadlineMinutes),
                'elapsed' => $elapsed,
                'overdue' => $elapsed > $this->deadlineMinutes,
                'overBy' => max(0, $elapsed - $this->deadlineMinutes),
                'remaining' => max(0, $this->deadlineMinutes - $elapsed),
            ];
        }

        return [
            'contacts' => $contacts,
            'next' => $next,
            'onTimeCount' => $onTime,
            'lateCount' => $late,
        ];
    }
}
