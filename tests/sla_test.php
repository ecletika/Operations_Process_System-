<?php

declare(strict_types=1);

/**
 * Testes da contagem do SLA — a regra que decide se um processo ficou dentro
 * ou fora do prazo e, por isso, se o operador recebe prémio.
 *
 * Não precisa de base de dados: o horário de atendimento, os feriados e as
 * definições são injetados nas caches internas por Reflection, para o teste
 * exercitar o código REAL (BusinessClock, sla_elapsed_minutes, a agregação do
 * Relatório SLA e a reconstrução das pausas).
 *
 * Uso: php tests/sla_test.php
 */

use App\Core\Settings;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Support\BusinessClock;
use App\Modules\Process\Support\SlaPauseRebuilder;
use App\Modules\Reports\Repositories\AnalyticsRepository;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

// A aplicação corre sempre em UTC (Env::load) e a base de dados guarda em UTC;
// a hora local só aparece na apresentação, via dt(). O teste replica isso —
// senão mediria o fuso da máquina em vez do horário configurado.
date_default_timezone_set('UTC');

/** Converte a hora de PAREDE (a que se vê no ecrã) no valor guardado em UTC. */
$utc = static fn (string $local): string => (new DateTimeImmutable($local, new DateTimeZone('Europe/Lisbon')))
    ->setTimezone(new DateTimeZone('UTC'))
    ->format('Y-m-d H:i:s');

$injetar = static function (string $class, string $prop, mixed $value): void {
    $r = new ReflectionProperty($class, $prop);
    $r->setAccessible(true);
    $r->setValue(null, $value);
};

// Horário de exemplo: Seg-Sex 08:30-12:30 e 14:00-18:00; fim de semana fechado.
$dia = ['open' => '08:30:00', 'close' => '18:00:00', 'lunch_start' => '12:30:00', 'lunch_end' => '14:00:00'];
$fechado = ['open' => null, 'close' => null, 'lunch_start' => null, 'lunch_end' => null];
$injetar(BusinessClock::class, 'hours', [0 => $fechado, 1 => $dia, 2 => $dia, 3 => $dia, 4 => $dia, 5 => $dia, 6 => $fechado]);
$injetar(BusinessClock::class, 'holidays', []);

$horarioLigado = static fn (bool $ligado) => $injetar(Settings::class, 'cache', ['sla_business_hours_enabled' => $ligado ? '1' : '0']);

$falhas = 0;
$check = static function (string $nome, mixed $obtido, mixed $esperado) use (&$falhas): void {
    $ok = $obtido === $esperado;
    if (!$ok) {
        $falhas++;
    }
    printf("  [%s] %s\n", $ok ? 'OK  ' : 'FALHA', $nome);
    if (!$ok) {
        printf("        obtido: %s\n      esperado: %s\n", var_export($obtido, true), var_export($esperado, true));
    }
};

// =====================================================================
echo "\n== Minutos de SLA com o horário de atendimento LIGADO ==\n";
$horarioLigado(true);

// O caso que deu origem a esta correção: entrou 16 min depois do fecho e foi
// fechado às 09:37 do dia seguinte. O relógio só arranca às 08:30.
$check('entrou depois do fecho: só conta a partir da abertura seguinte',
    sla_elapsed_minutes($utc('2026-08-25 18:16'), $utc('2026-08-26 09:37')), 67);
$check('fecho no próprio dia, já depois das 18:00, corta às 18:00',
    sla_elapsed_minutes($utc('2026-08-26 16:10'), $utc('2026-08-26 18:32')), 110);
$check('salta a hora de almoço',
    sla_elapsed_minutes($utc('2026-08-26 11:30'), $utc('2026-08-26 15:00')), 120);
$check('salta o fim de semana',
    sla_elapsed_minutes($utc('2026-08-28 17:00'), $utc('2026-08-31 09:00')), 90);
$check('fim antes do início não fica negativo',
    sla_elapsed_minutes($utc('2026-08-26 10:00'), $utc('2026-08-26 09:00')), 0);

// =====================================================================
echo "\n== Com o horário DESLIGADO nada muda (24h/dia, como antes) ==\n";
$horarioLigado(false);
$check('conta o tempo corrido, incluindo a noite',
    sla_elapsed_minutes($utc('2026-08-25 18:16'), $utc('2026-08-26 09:37')), 921);
$check('conta o tempo corrido no mesmo dia',
    sla_elapsed_minutes($utc('2026-08-26 16:10'), $utc('2026-08-26 18:32')), 142);

// =====================================================================
echo "\n== Agregação do Relatório SLA (equipa × prioridade, SLA de 120 min) ==\n";
$horarioLigado(true);

$repo = (new ReflectionClass(AnalyticsRepository::class))->newInstanceWithoutConstructor();
$agrega = new ReflectionMethod(AnalyticsRepository::class, 'agregaSla');
$agrega->setAccessible(true);
$within = new ReflectionMethod(AnalyticsRepository::class, 'withinSla');
$within->setAccessible(true);

$linha = static fn (string $equipa, int $batch, string $de, string $ate) => [
    'equipa' => $equipa, 'prioridade' => 'Normal', 'sla_minutos' => 120,
    'created_at' => $de, 'closed_at' => $ate, 'batch_id' => $batch, 'priority_id' => 2,
];

$out = $agrega->invoke($repo, [
    $linha('IL - 132 · Colisão', 7, $utc('2026-08-25 18:16'), $utc('2026-08-26 09:37')),  // 67 min
    $linha('IL - 132 · Colisão', 7, $utc('2026-08-26 16:10'), $utc('2026-08-26 18:32')),  // 110 min
    $linha('IL - 132 · CRM', 8, $utc('2026-08-24 09:00'), $utc('2026-08-26 09:00')),      // 2 dias úteis
], 'equipa', 'batch_id');

$check('agrupa por equipa × prioridade', count($out), 2);
$check('conta os processos concluídos', (int) $out[0]['concluidos'], 2);
$check('os dois de Colisão ficam DENTRO do SLA em horário útil', (int) $out[0]['dentro_sla'], 2);
$check('tempo médio = (67+110)/2', (int) $out[0]['tempo_medio_min'], 89);
$check('o de CRM fica FORA do SLA', (int) $out[1]['dentro_sla'], 0);
$check('devolve as mesmas colunas que a versão em SQL', array_keys($out[0]), [
    'equipa', 'prioridade', 'sla_minutos', 'concluidos', 'dentro_sla', 'tempo_medio_min', 'batch_id', 'priority_id',
]);
$check('sem SLA definido conta como fora', $within->invoke(null, 10, null), 0);
$check('dentro do SLA', $within->invoke(null, 10, 120), 1);
$check('fora do SLA', $within->invoke(null, 200, 120), 0);

// =====================================================================
echo "\n== Reconstrução das pausas do SLA a partir da Timeline ==\n";
$W = 'PROCESS_WAITING';
$R = 'PROCESS_RESUMED';
$ev = static fn (string $tipo, string $local) => ['event_type' => $tipo, 'created_at' => $utc($local)];

$check('espera pela noite: 960 min corridos passam a 90 úteis',
    SlaPauseRebuilder::minutesFromEvents([$ev($W, '2026-08-26 17:00'), $ev($R, '2026-08-27 09:00')]), 90);
$check('espera que atravessa o almoço',
    SlaPauseRebuilder::minutesFromEvents([$ev($W, '2026-08-26 11:30'), $ev($R, '2026-08-26 15:00')]), 120);
$check('duas esperas no mesmo processo somam-se',
    SlaPauseRebuilder::minutesFromEvents([
        $ev($W, '2026-08-26 09:00'), $ev($R, '2026-08-26 10:00'),
        $ev($W, '2026-08-26 15:00'), $ev($R, '2026-08-26 15:30'),
    ]), 90);
$check('espera ainda a decorrer não entra na conta',
    SlaPauseRebuilder::minutesFromEvents([
        $ev($W, '2026-08-26 09:00'), $ev($R, '2026-08-26 10:00'), $ev($W, '2026-08-26 15:00'),
    ]), 60);
$check('espera só ao sábado não credita nada',
    SlaPauseRebuilder::minutesFromEvents([$ev($W, '2026-08-29 10:00'), $ev($R, '2026-08-29 16:00')]), 0);
$check('histórico imperfeito: waiting repetido conta desde o primeiro',
    SlaPauseRebuilder::minutesFromEvents([
        $ev($W, '2026-08-26 09:00'), $ev($W, '2026-08-26 09:30'), $ev($R, '2026-08-26 10:00'),
    ]), 60);
$check('histórico imperfeito: resumed sem waiting é ignorado',
    SlaPauseRebuilder::minutesFromEvents([
        $ev($R, '2026-08-26 09:00'), $ev($W, '2026-08-26 10:00'), $ev($R, '2026-08-26 11:00'),
    ]), 60);
$check('processo sem eventos de espera', SlaPauseRebuilder::minutesFromEvents([]), 0);

$eventos = [$ev($W, '2026-08-26 17:00'), $ev($R, '2026-08-27 09:00')];
$check('recálculo é idempotente (correr duas vezes dá o mesmo)',
    SlaPauseRebuilder::minutesFromEvents($eventos), SlaPauseRebuilder::minutesFromEvents($eventos));

$horarioLigado(false);
$check('com o horário desligado a pausa volta a contar 24h/dia',
    SlaPauseRebuilder::minutesFromEvents([$ev($W, '2026-08-26 17:00'), $ev($R, '2026-08-27 09:00')]), 960);

// =====================================================================
echo "\n== Contrato de changeStatus (a pausa vem calculada de fora) ==\n";
$assinatura = new ReflectionMethod(ProcessRepository::class, 'changeStatus');
$check('aceita os minutos de pausa já calculados', $assinatura->getNumberOfParameters(), 5);
$check('o último parâmetro é opcional', $assinatura->getParameters()[4]->isOptional(), true);

echo "\n" . ($falhas === 0 ? "TODOS OS TESTES PASSARAM\n\n" : "{$falhas} TESTE(S) FALHARAM\n\n");
exit($falhas === 0 ? 0 : 1);
