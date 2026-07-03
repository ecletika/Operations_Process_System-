<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

/**
 * Resultado da tentativa de criação de um Processo (RN-0017 a RN-0024).
 */
final class ProcessResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?int $processId = null,
        public readonly ?string $processNumber = null,
    ) {
    }

    public static function created(int $processId, string $processNumber): self
    {
        return new self('created', $processId, $processNumber);
    }

    /** RN-0018 - processo aberto já existia; apenas somámos uma interação. */
    public static function duplicateInteractionAdded(int $processId, string $processNumber): self
    {
        return new self('duplicate_interaction_added', $processId, $processNumber);
    }

    /** RN-0022 - existe um processo encerrado recente; falta decisão do operador. */
    public static function needsReopenDecision(int $processId, string $processNumber): self
    {
        return new self('needs_reopen_decision', $processId, $processNumber);
    }
}
