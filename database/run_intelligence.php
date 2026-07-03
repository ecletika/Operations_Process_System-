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
use App\Modules\Intelligence\Services\IntelligenceService;

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

    fwrite(STDOUT, "[{$startedAt}] OK - processos esquecidos notificados: {$forgotten}; SLA próximo notificados: {$slaNear}\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "[{$startedAt}] ERRO - {$e->getMessage()}\n");
    exit(1);
}
