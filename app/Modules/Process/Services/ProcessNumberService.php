<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Core\Database;

/**
 * RN-0002 - Formato PR-AAAA-XXXXXXXX, nunca reutilizado (fn_process_number()
 * do OPS-SQL-001 §10, implementada em PHP com contador atómico por ano).
 */
final class ProcessNumberService
{
    public function next(): string
    {
        $pdo = Database::connection();
        $year = (int) date('Y');

        // Idiom MySQL para contador atómico sem race condition. O VALUES()
        // também tem de vir envolvido em LAST_INSERT_ID(): sem isso, a
        // primeira chamada do ano (que insere em vez de atualizar) nunca
        // define o "last insert id" da sessão e o contador arranca em 0
        // em vez de 1.
        $pdo->prepare('
            INSERT INTO tb_process_sequence (year, last_value)
            VALUES (:year, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)
        ')->execute(['year' => $year]);

        $sequence = (int) $pdo->lastInsertId();

        return sprintf('PR-%d-%08d', $year, $sequence);
    }
}
