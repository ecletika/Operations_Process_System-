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
        $this->autoScheduleNextContact($processId, $operatorId);

        $this->timeline->record(
            $processId,
            'INTERACTION_CREATED',
            'Nova interação registada',
            $description,
            $operatorId
        );
    }

    /**
     * Só para Imobilizados (a combinação Assunto/Prioridade configurada em
     * Configurações): cada contacto registado conta como "já liguei agora" e
     * empurra o próximo contacto para mais X minutos de atendimento à frente.
     * Fora dessa combinação não há agendamento automático.
     */
    private function autoScheduleNextContact(int $processId, int $userId): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null || !ProcessService::allowsNextContact($process)) {
            return;
        }

        $minutes = ProcessService::autoNextContactMinutes($process);
        if ($minutes <= 0) {
            return;
        }

        $this->processes->setNextContactAt($processId, ProcessService::nextContactDeadline($minutes), $userId);
        $this->timeline->record(
            $processId,
            'NEXT_CONTACT_SET',
            "Próximo contacto com o cliente reagendado automaticamente (+{$minutes} min)",
            null,
            $userId
        );
    }

    public function listByProcess(int $processId): array
    {
        return $this->interactions->listByProcess($processId);
    }
}
