<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Modules\Process\Repositories\InteractionRepository;
use App\Modules\Process\Repositories\ProcessRepository;

/**
 * OPS-PRD-001 §7.4 (RN-0013 a RN-0016) / RF-0016 / RF-0017.
 */
final class InteractionService
{
    public function __construct(
        private readonly InteractionRepository $interactions = new InteractionRepository(),
        private readonly ProcessRepository $processes = new ProcessRepository(),
        private readonly TimelineService $timeline = new TimelineService(),
    ) {
    }

    /**
     * RN-0013/14/15/16 - cada contacto gera uma Interação, nunca um novo
     * Processo; incrementa contact_count; atualiza last_contact_at; gera Evento.
     */
    public function addInteraction(
        int $processId,
        string $type,
        string $channel,
        string $description,
        int $operatorId
    ): void {
        $this->interactions->create($processId, $type, $channel, $description, $operatorId);
        $this->processes->registerContact(
            $processId,
            (string) \App\Core\Settings::get('sla_renew_on_interaction', '1') === '1'
        );

        $this->timeline->record(
            $processId,
            'INTERACTION_CREATED',
            'Nova interação registada',
            $description,
            $operatorId
        );
    }

    public function listByProcess(int $processId): array
    {
        return $this->interactions->listByProcess($processId);
    }
}
