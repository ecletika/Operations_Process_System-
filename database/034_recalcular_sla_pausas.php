<?php

declare(strict_types=1);

/**
 * Recálculo retroativo do tempo de PAUSA do SLA (tb_process.sla_paused_minutes).
 *
 * Até agora, sempre que um processo saía de "em espera", o tempo dessa pausa
 * era somado com TIMESTAMPDIFF — tempo corrido. Com o horário de atendimento
 * ligado isso está errado: uma espera das 17h às 9h do dia seguinte creditava
 * 16 horas de pausa, quando de expediente só passou 1h30m. Esse crédito a mais
 * é somado ao prazo e faz processos parecerem cumpridos quando não foram
 * (ou o contrário, conforme o caso).
 *
 * Este script reconstrói as pausas a partir dos eventos PROCESS_WAITING /
 * PROCESS_RESUMED da Timeline — que são imutáveis (RN-0026) — e volta a somar
 * cada uma com a regra de SLA em vigor, via sla_elapsed_minutes().
 *
 * É IDEMPOTENTE: recalcula sempre do zero a partir dos eventos, por isso pode
 * correr as vezes que forem precisas.
 *
 * Uso:
 *   php database/034_recalcular_sla_pausas.php            → simulação (não grava)
 *   php database/034_recalcular_sla_pausas.php --aplicar   → grava as alterações
 */

use App\Core\Database;
use App\Core\Env;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

Env::load(__DIR__ . '/../.env');

$aplicar = in_array('--aplicar', $argv, true);
$pdo = Database::connection();

if (!\App\Modules\Process\Support\BusinessClock::enabled()) {
    echo "AVISO: \"Contar o SLA apenas em horário de atendimento\" está DESLIGADO.\n";
    echo "Com essa definição desligada a contagem é 24h/dia e não há nada a corrigir.\n";
    echo "Ligue-a em Configurações → SLA antes de correr este recálculo.\n";
    exit(1);
}

echo $aplicar
    ? "MODO REAL — as alterações vão ser gravadas.\n\n"
    : "SIMULAÇÃO — nada é gravado. Use --aplicar para gravar.\n\n";

// Processos que já estiveram em espera alguma vez, ou que têm pausa acumulada.
$processos = $pdo->query("
    SELECT p.id, p.process_number, p.sla_paused_minutes, p.wait_started_at
    FROM tb_process p
    WHERE p.deleted_at IS NULL
      AND (p.sla_paused_minutes <> 0
           OR EXISTS (SELECT 1 FROM tb_event e
                      WHERE e.process_id = p.id AND e.event_type = 'PROCESS_WAITING'))
    ORDER BY p.id ASC
")->fetchAll();

$eventos = $pdo->prepare("
    SELECT event_type, created_at
    FROM tb_event
    WHERE process_id = :id
      AND event_type IN ('PROCESS_WAITING', 'PROCESS_RESUMED')
      AND deleted_at IS NULL
    ORDER BY created_at ASC, id ASC
");

$update = $pdo->prepare('UPDATE tb_process SET sla_paused_minutes = :minutos WHERE id = :id');

$alterados = 0;
$iguais = 0;
$semHistorico = 0;
$totalAntes = 0;
$totalDepois = 0;

foreach ($processos as $processo) {
    $id = (int) $processo['id'];
    $antes = (int) $processo['sla_paused_minutes'];

    $eventos->execute(['id' => $id]);
    $linhas = $eventos->fetchAll();

    // Sem eventos de espera mas com pausa gravada: veio de antes de a Timeline
    // registar isto. Não há como reconstruir — não se toca, para não inventar.
    if ($linhas === [] && $antes !== 0) {
        $semHistorico++;
        printf("  ? %-18s %5d min — sem eventos de espera na Timeline; deixado como está\n",
            (string) $processo['process_number'], $antes);
        continue;
    }

    $depois = \App\Modules\Process\Support\SlaPauseRebuilder::minutesFromEvents($linhas);

    $totalAntes += $antes;
    $totalDepois += $depois;

    if ($depois === $antes) {
        $iguais++;
        continue;
    }

    $alterados++;
    printf("  %s %-18s %5d min → %5d min (%+d)\n",
        $aplicar ? '✓' : '·', (string) $processo['process_number'], $antes, $depois, $depois - $antes);

    if ($aplicar) {
        $update->execute(['id' => $id, 'minutos' => $depois]);
    }
}

echo "\n";
printf("Processos analisados......: %d\n", count($processos));
printf("Já estavam certos.........: %d\n", $iguais);
printf("Corrigidos................: %d%s\n", $alterados, $aplicar ? '' : ' (por aplicar)');
printf("Sem histórico na Timeline.: %d (não tocados)\n", $semHistorico);
printf("Pausa total antes.........: %d min\n", $totalAntes);
printf("Pausa total depois........: %d min (%+d)\n", $totalDepois, $totalDepois - $totalAntes);

if (!$aplicar && $alterados > 0) {
    echo "\nPara gravar: php database/034_recalcular_sla_pausas.php --aplicar\n";
}
