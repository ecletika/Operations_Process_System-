<?php

declare(strict_types=1);

namespace App\Core;

/**
 * OPS-PRD-001 §11.20 - logs/application.log, security.log, etc.
 * Ficheiro simples baseado em texto; a auditoria "de negócio" (quem alterou
 * o quê) já vive em tb_audit — isto é para eventos de sistema/segurança que
 * queremos poder inspecionar mesmo que a base de dados esteja em baixo.
 */
final class Logger
{
    public static function write(string $channel, string $message): void
    {
        $dir = base_path('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
        @file_put_contents("{$dir}/{$channel}.log", $line, FILE_APPEND | LOCK_EX);
    }

    public static function security(string $message): void
    {
        self::write('security', $message);
    }
}
