<?php

declare(strict_types=1);

/**
 * Job agendado da Fase 4 — Inteligência Operacional™ (OPS-PRD-001 §7.18).
 * Corre RN-0056 (Processo Esquecido) e RF-0039 (SLA Próximo).
 *
 * Agendar via cron do SO a cada 15 minutos (ver README.md para a linha
 * completa de crontab — omitida aqui porque `* / *` fecharia este comentário).
 */

use App\Core\Env;
use App\Core\Settings;
use App\Modules\Administration\Repositories\AuditRepository;
use App\Modules\Intelligence\Services\IntelligenceService;
use App\Modules\Process\Repositories\ProcessRepository;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

Env::load(__DIR__ . '/../.env');

$startedAt = date('Y-m-d H:i:s');

try {
    $service = new IntelligenceService();

    $forgotten = $service->detectForgottenProcesses();
    $slaNear = $service->detectSlaNear();

    // Ciclo de vida de arquivamento: arquivar concluídos antigos e excluir
    // (soft-delete → Lixeira) os arquivados há mais de X dias.
    $processes = new ProcessRepository();
    $archived = $processes->autoArchiveConcluded((int) Settings::get('archive_concluded_after_days', 30));
    $autoDeleted = $processes->autoDeleteArchived((int) Settings::get('delete_archived_after_days', 180));

    // Retenção da auditoria (política do cliente).
    $auditPurged = (new AuditRepository())->purgeOlderThan((int) Settings::get('audit_retention_days', 60));

    fwrite(STDOUT, "[{$startedAt}] OK - esquecidos: {$forgotten}; SLA próximo: {$slaNear}; arquivados: {$archived}; auto-excluídos: {$autoDeleted}; auditoria apagada: {$auditPurged}\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "[{$startedAt}] ERRO - {$e->getMessage()}\n");
    exit(1);
}
