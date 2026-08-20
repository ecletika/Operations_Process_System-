<?php

declare(strict_types=1);

/**
 * Backfill do Próximo Contacto dos Imobilizados que ficaram SEM próximo.
 *
 * Contexto: até à correção, o próximo contacto só era agendado quando alguém
 * registava uma interação/observação — processos com só o contacto de criação
 * ficavam com next_contact_at a NULL. Este script preenche esses casos,
 * calculando next_contact = último contacto + X min ÚTEIS (o mesmo relógio do
 * SLA: horário de atendimento, salta fins de semana/feriados).
 *
 * Só toca em processos ABERTOS, da combinação Imobilizado configurada
 * (next_contact_subject_code / next_contact_priority_code), com next_contact
 * a NULL e último contacto conhecido. Não altera datas já preenchidas.
 *
 * Uso (no servidor):  php database/scripts/backfill_next_contact.php
 * Simulação (não grava): php database/scripts/backfill_next_contact.php --dry-run
 */

require __DIR__ . '/../../app/Core/autoload.php';
require __DIR__ . '/../../app/Helpers/functions.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Settings;
use App\Modules\Process\Services\ProcessService;

Env::load(__DIR__ . '/../../.env');

$dryRun = in_array('--dry-run', $argv, true);
$pdo = Database::connection();

$subject = trim((string) Settings::get('next_contact_subject_code', 'IMO'));
$priority = trim((string) Settings::get('next_contact_priority_code', 'P4'));

$hasMinutes = Database::hasColumn('tb_priority', 'next_contact_auto_minutes');
$minutesExpr = $hasMinutes ? 'COALESCE(NULLIF(pr.next_contact_auto_minutes, 0), 960)' : '960';

$sql = "
    SELECT p.id, p.process_number, p.last_contact_at, {$minutesExpr} AS minutos
    FROM tb_process p
    JOIN tb_subject sub ON sub.id = p.subject_id
    JOIN tb_priority pr ON pr.id = p.priority_id
    JOIN tb_status st ON st.id = p.status_id
    WHERE p.deleted_at IS NULL
      AND p.next_contact_at IS NULL
      AND p.last_contact_at IS NOT NULL
      AND st.code NOT IN ('SOLVED', 'CLOSED')
      AND (:subj = '' OR sub.code = :subj)
      AND (:pri = '' OR pr.code = :pri)
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['subj' => $subject, 'pri' => $priority]);
$rows = $stmt->fetchAll();

$update = $pdo->prepare('UPDATE tb_process SET next_contact_at = :d, updated_at = NOW() WHERE id = :id');

$n = 0;
foreach ($rows as $row) {
    $fromTs = (new DateTimeImmutable((string) $row['last_contact_at'], new DateTimeZone('UTC')))->getTimestamp();
    $quando = ProcessService::nextContactDeadline((int) $row['minutos'], $fromTs); // 'Y-m-d H:i:s' UTC

    $local = (new DateTimeImmutable($quando, new DateTimeZone('UTC')))->setTimezone(app_timezone())->format('d/m/Y H:i');
    fwrite(STDOUT, sprintf("  %-18s último %s → próximo %s (%d min)\n", $row['process_number'], $row['last_contact_at'], $local, (int) $row['minutos']));

    if (!$dryRun) {
        $update->execute(['d' => $quando, 'id' => (int) $row['id']]);
    }
    $n++;
}

fwrite(STDOUT, "\n" . ($dryRun ? "[SIMULAÇÃO] " : "") . "{$n} processo(s) " . ($dryRun ? "seriam atualizados" : "atualizados") . ".\n");
