<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Core\Settings;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Process\DTO\CreateProcessDTO;
use App\Modules\Process\Repositories\CustomerRepository;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Repositories\VehicleRepository;
use App\Traits\AuditTrait;
use RuntimeException;

/**
 * Toda a regra de negócio do Processo vive aqui (OPS-PRD-001 11.7).
 * Nunca falamos diretamente com PDO fora dos Repositories.
 */
final class ProcessService
{
    use AuditTrait;

    /**
     * RN-0029 - um Processo nunca pode saltar estados inválidos.
     * Mapa de transições permitidas por código de estado.
     */
    private const TRANSITIONS = [
        // QUEUE -> SOLVED: um processo pode ser resolvido sem passar por
        // "Assumir" primeiro (ex.: o próprio operador que cria já resolve
        // o caso na hora). RF-0014 não exige assumir antes de concluir.
        'QUEUE' => ['ASSIGNED', 'SOLVED'],
        'ASSIGNED' => ['IN_PROGRESS', 'WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL', 'SOLVED'],
        'IN_PROGRESS' => ['WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL', 'SOLVED'],
        'WAIT_CLIENT' => ['IN_PROGRESS', 'SOLVED'],
        'WAIT_PARTS' => ['IN_PROGRESS', 'SOLVED'],
        'WAIT_WORKSHOP' => ['IN_PROGRESS', 'SOLVED'],
        'WAIT_EXTERNAL' => ['IN_PROGRESS', 'SOLVED'],
        // Reabrir devolve o processo à Fila Inteligente™ para reatribuição
        // (o estado "REOPENED" do dicionário fica reservado para relatórios/analítica).
        'SOLVED' => ['CLOSED', 'QUEUE'],
        'CLOSED' => ['QUEUE'],
    ];

    public function __construct(
        private readonly ProcessRepository $processes = new ProcessRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly VehicleRepository $vehicles = new VehicleRepository(),
        private readonly ProcessNumberService $numbers = new ProcessNumberService(),
        private readonly TimelineService $timeline = new TimelineService(),
        private readonly InteractionService $interactions = new InteractionService(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /**
     * RF-0009 / RN-0009 / RN-0017 a RN-0024.
     */
    public function create(CreateProcessDTO $dto, int $userId, int $companyId, int $batchId): ProcessResult
    {
        $vehicle = $this->vehicles->findByPlate($dto->plate);

        if ($vehicle !== null) {
            $customerId = (int) $vehicle['customer_id'];
        } else {
            // RF-0009 - Tipo de Interação decide se o cliente se identifica
            // por telefone (Telefone/WhatsApp) ou por email (Email/Presencial
            // com email); nunca os dois obrigatórios ao mesmo tempo.
            $customer = $dto->customerPhone !== null
                ? $this->customers->findByPhone($dto->customerPhone)
                : ($dto->customerEmail !== null ? $this->customers->findByEmail($dto->customerEmail) : null);

            $customerId = $customer !== null
                ? (int) $customer['id']
                : $this->customers->create($dto->customerName, $dto->customerPhone, $dto->customerEmail, $userId);
        }

        $vehicleId = $vehicle !== null
            ? (int) $vehicle['id']
            : $this->vehicles->create($dto->plate, $customerId, $userId);

        // RN-0017/0018 - já existe processo aberto com a mesma matrícula + assunto?
        $openDuplicate = $this->processes->findOpenByVehicleAndSubject($vehicleId, $dto->subjectId);
        if ($openDuplicate !== null) {
            $this->interactions->addInteraction(
                (int) $openDuplicate['id'],
                $dto->contactChannel,
                $dto->contactChannel,
                $dto->description,
                $userId
            );

            return ProcessResult::duplicateInteractionAdded((int) $openDuplicate['id'], $openDuplicate['process_number']);
        }

        // RN-0021/0022 - janela de reincidência: processo encerrado há menos de X dias.
        $windowDays = (int) Settings::get('reopen_window_days', 30);
        $recentlyClosed = $this->processes->findRecentlyClosedByVehicleAndSubject($vehicleId, $dto->subjectId, $windowDays);

        if ($recentlyClosed !== null && !$dto->reopenIfEligible) {
            // O operador ainda não decidiu; o Controller deve perguntar
            // "Deseja reabrir o Processo?" e reenviar com reopen_if_eligible=1/0.
            return ProcessResult::needsReopenDecision((int) $recentlyClosed['id'], $recentlyClosed['process_number']);
        }

        if ($recentlyClosed !== null && $dto->reopenIfEligible) {
            $this->reopen((int) $recentlyClosed['id'], $userId);
            $this->interactions->addInteraction(
                (int) $recentlyClosed['id'],
                $dto->contactChannel,
                $dto->contactChannel,
                $dto->description,
                $userId
            );

            return ProcessResult::duplicateInteractionAdded((int) $recentlyClosed['id'], $recentlyClosed['process_number']);
        }

        // RN-0023/0024 (NÃO reabrir, ou sem histórico recente) - cria mesmo um novo Processo.
        $processNumber = $this->numbers->next();
        $queueStatusId = $this->processes->statusIdByCode('QUEUE');

        $processId = $this->processes->create([
            'process_number' => $processNumber,
            'company_id' => $companyId,
            'batch_id' => $batchId,
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'subject_id' => $dto->subjectId,
            'status_id' => $queueStatusId,
            'priority_id' => $dto->priorityId,
            'created_by' => $userId,
        ]);

        $this->timeline->record($processId, 'PROCESS_CREATED', "Processo {$processNumber} criado", $dto->description, $userId);

        // RF-0038 - notifica supervisores/administradores da empresa.
        $this->notifications->notifySupervisors(
            $companyId,
            "Novo processo: {$processNumber}",
            "Assunto: {$dto->description}",
            'INFO'
        );

        return ProcessResult::created($processId, $processNumber);
    }

    /**
     * RF-0012 / RN-0011 / RN-0012 - transação com row lock, um único operador
     * consegue assumir o processo.
     */
    public function assume(int $processId, int $userId): void
    {
        // RN-0057 - operador sobrecarregado deixa de poder assumir novos processos.
        $overloadLimit = (int) Settings::get('operator_overload_limit', 30);
        if ($this->users->activeProcessCount($userId) >= $overloadLimit) {
            throw new RuntimeException("Já tem {$overloadLimit} ou mais processos ativos. Conclua alguns antes de assumir novos.");
        }

        $pdo = $this->processes->pdo();
        $pdo->beginTransaction();

        try {
            $process = $this->processes->lockForUpdate($processId);

            if ($process === null) {
                throw new RuntimeException('Processo não encontrado.');
            }

            $currentStatus = $this->processes->statusCodeById((int) $process['status_id']);
            $this->guardTransition($currentStatus, 'ASSIGNED');

            $assignedStatusId = $this->processes->statusIdByCode('ASSIGNED');
            $this->processes->assign($processId, $userId, $assignedStatusId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->timeline->record($processId, 'PROCESS_ASSIGNED', 'Processo assumido', null, $userId);
    }

    /**
     * RF-0014 / RN-0040 a RN-0044 - o SLA e os tempos são sempre calculados
     * a partir dos timestamps, nunca armazenados (decisão arquitetural §10.20).
     * V1 conclui diretamente para "Resolvido" (SOLVED); o arquivamento para
     * "Encerrado" (CLOSED) fica para o módulo de Administração/Relatórios.
     */
    public function close(int $processId, int $userId): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardTransition($process['status_code'], 'SOLVED');

        $solvedStatusId = $this->processes->statusIdByCode('SOLVED');
        $this->processes->close($processId, $solvedStatusId, $userId);

        $this->timeline->record($processId, 'PROCESS_CLOSED', 'Processo concluído', null, $userId);
    }

    /**
     * Exclusão de Processo - restrita a Administrador (permissão
     * process.delete verificada no Controller/rota). Continua a ser soft
     * delete: o histórico fica preservado, só deixa de aparecer nas listas.
     */
    public function delete(int $processId, int $userId): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->processes->delete($processId, $userId);
        $this->logAudit('DELETE', 'tb_process', $processId, ['process_number' => $process['process_number']], null);
    }

    /**
     * RF-0015 / RN-0021 a RN-0023 - reabre um processo encerrado.
     */
    public function reopen(int $processId, int $userId): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardTransition($process['status_code'], 'QUEUE');

        $queueStatusId = $this->processes->statusIdByCode('QUEUE');
        $this->processes->reopen($processId, $queueStatusId, $userId);

        $this->timeline->record($processId, 'PROCESS_REOPENED', 'Processo reaberto', null, $userId);
    }

    private function guardTransition(string $currentStatusCode, string $targetStatusCode): void
    {
        $allowed = self::TRANSITIONS[$currentStatusCode] ?? [];

        if (!in_array($targetStatusCode, $allowed, true)) {
            throw new RuntimeException("Transição inválida: {$currentStatusCode} → {$targetStatusCode}.");
        }
    }

    /**
     * DNA do Processo™ (OPS-PRD-001 §2.11) - resumo executivo calculado a
     * partir dos timestamps reais, nunca de valores armazenados (§10.20).
     * RN-0040 a RN-0044.
     */
    public function calculateDna(array $process, int $eventsTotal): array
    {
        $createdAt = new \DateTimeImmutable($process['created_at']);
        $end = $process['closed_at'] !== null
            ? new \DateTimeImmutable($process['closed_at'])
            : new \DateTimeImmutable('now');

        $totalMinutes = (int) (($end->getTimestamp() - $createdAt->getTimestamp()) / 60);

        $timeToAssumeMinutes = null;
        if ($process['assumed_at'] !== null) {
            $assumedAt = new \DateTimeImmutable($process['assumed_at']);
            $timeToAssumeMinutes = (int) (($assumedAt->getTimestamp() - $createdAt->getTimestamp()) / 60);
        }

        $slaMinutes = $process['default_sla_minutes'] !== null ? (int) $process['default_sla_minutes'] : null;
        $slaMet = $slaMinutes !== null ? $totalMinutes <= $slaMinutes : null;

        return [
            'total_minutes' => $totalMinutes,
            'time_to_assume_minutes' => $timeToAssumeMinutes,
            'contact_count' => (int) $process['contact_count'],
            'reopen_count' => (int) $process['reopen_count'],
            'events_total' => $eventsTotal,
            'sla_minutes' => $slaMinutes,
            'sla_met' => $slaMet,
        ];
    }
}
