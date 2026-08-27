<?php

declare(strict_types=1);

namespace App\Modules\Process\Support;

/**
 * Reconstrói o tempo de pausa do SLA de um processo a partir dos eventos
 * PROCESS_WAITING / PROCESS_RESUMED da Timeline — que nunca são alterados
 * nem apagados (RN-0026), e por isso servem de fonte da verdade quando é
 * preciso recalcular pausas antigas.
 *
 * Usado pelo recálculo retroativo (database/034_recalcular_sla_pausas.php).
 */
final class SlaPauseRebuilder
{
    /**
     * Minutos de pausa das esperas já TERMINADAS. Uma espera ainda a decorrer
     * (waiting sem resumed no fim) não entra: o relógio ao vivo trata dela
     * pelo wait_started_at do processo.
     *
     * Tolera histórico imperfeito: waiting repetidos sem resumed pelo meio
     * contam como uma única espera (a partir do primeiro), e um resumed sem
     * waiting antes é ignorado.
     *
     * @param list<array{event_type:string, created_at:string}> $eventos por created_at ASC
     */
    public static function minutesFromEvents(array $eventos): int
    {
        $minutos = 0;
        $inicio = null;

        foreach ($eventos as $evento) {
            if ($evento['event_type'] === 'PROCESS_WAITING') {
                $inicio ??= (string) $evento['created_at'];
                continue;
            }

            if ($evento['event_type'] === 'PROCESS_RESUMED' && $inicio !== null) {
                $minutos += sla_elapsed_minutes($inicio, (string) $evento['created_at']);
                $inicio = null;
            }
        }

        return $minutos;
    }
}
