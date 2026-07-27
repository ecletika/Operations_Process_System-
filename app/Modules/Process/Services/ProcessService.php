<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Core\Settings;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Process\DTO\CreateProcessDTO;
use App\Modules\Process\Repositories\CustomerRepository;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Repositories\StatusRepository;
use App\Modules\Process\Repositories\VehicleRepository;
use App\Modules\Process\Support\BusinessClock;
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
        // "→ QUEUE": o operador pode devolver o processo à fila quando não
        // consegue tratá-lo (ex.: indisponível), para outro assumir.
        'ASSIGNED' => ['IN_PROGRESS', 'WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL', 'SOLVED', 'QUEUE'],
        'IN_PROGRESS' => ['WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL', 'SOLVED', 'QUEUE'],
        'WAIT_CLIENT' => ['IN_PROGRESS', 'SOLVED', 'QUEUE'],
        'WAIT_PARTS' => ['IN_PROGRESS', 'SOLVED', 'QUEUE'],
        'WAIT_WORKSHOP' => ['IN_PROGRESS', 'SOLVED', 'QUEUE'],
        'WAIT_EXTERNAL' => ['IN_PROGRESS', 'SOLVED', 'QUEUE'],
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

        if ($vehicle !== null) {
            $vehicleId = (int) $vehicle['id'];
            // Se a viatura já existia mas não tinha marca/modelo (ex.: criada
            // antes deste campo), aproveita o que o operador escreveu agora.
            $this->vehicles->fillMissingBrandModel($vehicleId, $dto->vehicleBrand, $dto->vehicleModel, $userId);
        } else {
            $vehicleId = $this->vehicles->create($dto->plate, $customerId, $userId, $dto->vehicleBrand, $dto->vehicleModel);
        }

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

        // Regista para que departamento o processo nasceu — aparece no Replay
        // e permite rastrear a origem mesmo depois de transferências.
        $departamento = $this->processes->batchLabel((int) $batchId);
        $this->timeline->record(
            $processId,
            'PROCESS_CREATED',
            "Processo {$processNumber} criado para {$departamento}",
            $dto->description,
            $userId
        );

        // RF-0038 - notifica supervisores/administradores da empresa.
        $this->notifications->notifySupervisors(
            $companyId,
            "Novo processo: {$processNumber}",
            "Assunto: {$dto->description}",
            'INFO'
        );

        // #6 - Pop-up de nova lead para os elementos do departamento de destino
        // (o lote onde o processo entra na fila), exceto quem o criou.
        $this->notifications->notifyBatchUsers(
            $batchId,
            "📥 Nova lead na fila: {$processNumber}",
            "Novo pedido no seu departamento. Abra a Fila Inteligente™ para assumir.",
            'INFO',
            $userId
        );

        return ProcessResult::created($processId, $processNumber);
    }

    /**
     * RF-0012 / RN-0011 / RN-0012 - transação com row lock, um único operador
     * consegue assumir o processo.
     */
    /**
     * @param array<int>|null $allowedBatchIds Isolamento por departamento
     *   (RN-0011): quando não é null, o operador só pode assumir processos
     *   cujo lote esteja nesta lista (o seu departamento). null = sem
     *   restrição (Supervisor/Admin ou "ver todos os lotes").
     */
    public function assume(int $processId, int $userId, ?array $allowedBatchIds = null): void
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

            // Isolamento por departamento: um operador não pode assumir um
            // processo de um departamento que não é o seu.
            if ($allowedBatchIds !== null && !in_array((int) $process['batch_id'], $allowedBatchIds, true)) {
                throw new RuntimeException('Este processo pertence a outro departamento; não o pode assumir.');
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
     * Motivos de Pausa do SLA — estados marcados com is_waiting na tabela de
     * estados. São configuráveis pelo Administrador (Configurações → Motivos
     * de Pausa do SLA), por isso vêm da base e não de uma lista fixa.
     *
     * Inclui os inativos de propósito: um motivo pode ser desativado enquanto
     * ainda há processos nesse estado, e esses têm de poder sair de lá.
     *
     * @return string[]
     */
    public function waitingCodes(): array
    {
        static $codes = null;

        if ($codes === null) {
            $codes = (new StatusRepository())->waitingCodes(onlyActive: false);
        }

        return $codes;
    }

    /**
     * Muda o estado do processo (ex.: "Aguarda Cliente" quando o cliente não
     * atende, ou retomar o tratamento). Enquanto está à espera, o relógio do
     * SLA fica parado — o operador não é penalizado por demoras que não
     * dependem dele. O "Tempo Total" (espera real do cliente) não é afetado.
     */
    public function changeStatus(int $processId, string $targetStatusCode, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);
        $this->guardTransition($process['status_code'], $targetStatusCode);

        $isWaiting = in_array($targetStatusCode, $this->waitingCodes(), true);
        $statusId = $this->processes->statusIdByCode($targetStatusCode);
        $this->processes->changeStatus($processId, $statusId, $userId, $isWaiting);

        $label = $this->processes->statusNameByCode($targetStatusCode);
        $this->timeline->record(
            $processId,
            $isWaiting ? 'PROCESS_WAITING' : 'PROCESS_RESUMED',
            $isWaiting ? "Em espera: {$label} (SLA em pausa)" : "Tratamento retomado ({$label}) — SLA a contar",
            null,
            $userId
        );

        $this->syncNextContactWithWaiting($process, $isWaiting, $userId);
    }

    /**
     * Ao pôr o processo em espera, agenda logo o próximo contacto com o cliente
     * conforme o intervalo da Prioridade (Configurações → Prioridades), para
     * o cliente não ficar esquecido enquanto o SLA está em pausa.
     *
     * Retomar o tratamento NÃO apaga a data: a cadência de contacto continua
     * também com o processo em tratamento, e cada contacto registado empurra-a
     * para a frente. Só mexe na data quando a Prioridade tem intervalo
     * definido — uma data marcada à mão nunca é tocada por aqui.
     *
     * @param array<string, mixed> $process estado do processo ANTES da mudança
     */
    private function syncNextContactWithWaiting(array $process, bool $isWaiting, int $userId): void
    {
        if (!$isWaiting) {
            return;
        }

        $minutes = self::autoNextContactMinutes($process);
        if ($minutes <= 0) {
            return;
        }

        $processId = (int) $process['id'];
        $quando = self::nextContactDeadline($minutes);
        $this->processes->setNextContactAt($processId, $quando, $userId);
        $this->timeline->record(
            $processId,
            'NEXT_CONTACT_SET',
            'Próximo contacto com o cliente agendado automaticamente para '
                . self::formatLocal($quando) . " ({$minutes} min, SLA em pausa)",
            null,
            $userId
        );
    }

    /**
     * A Nova Data de Contacto só se aplica à combinação configurada em
     * Configurações (por omissão prioridade "Baixa" + assunto "Imobilizados"),
     * e as duas têm de acontecer em simultâneo. Deixar um dos parâmetros
     * vazio significa "qualquer".
     *
     * @param array<string, mixed> $process linha do processo (com priority_code/subject_code)
     */
    public static function allowsNextContact(array $process): bool
    {
        $priority = trim((string) Settings::get('next_contact_priority_code', 'P4'));
        $subject = trim((string) Settings::get('next_contact_subject_code', 'IMO'));

        $priorityOk = $priority === '' || (string) ($process['priority_code'] ?? '') === $priority;
        $subjectOk = $subject === '' || (string) ($process['subject_code'] ?? '') === $subject;

        return $priorityOk && $subjectOk;
    }

    /**
     * De quantos em quantos minutos se contacta o cliente enquanto o SLA está
     * em pausa — vem da Prioridade do processo (Configurações → Prioridades),
     * na mesma unidade do SLA. 0 significa "sem lembrete automático": a data
     * é escolhida à mão no calendário da ficha do processo.
     *
     * @param array<string, mixed> $process linha do processo (com next_contact_auto_minutes)
     */
    public static function autoNextContactMinutes(array $process): int
    {
        $minutes = (int) ($process['next_contact_auto_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : 0;
    }

    /**
     * Quando cai o próximo contacto, já em UTC ('Y-m-d H:i:s') como o resto do
     * sistema guarda.
     *
     * Os minutos são contados no relógio de atendimento quando o horário útil
     * está ligado (mesma regra do SLA): de propósito, para nunca cair a um
     * sábado ou domingo — é precisamente por isto que a Prioridade se
     * configura em MINUTOS DE ATENDIMENTO e não em horas corridas. Ex.: para
     * "2 dias" o valor certo é 16h (960 min) de atendimento, não 48h (2880
     * min) — 48h corridas nunca caem num fim de semana só por acaso, mas
     * também não são "2 dias de trabalho". Com o horário desligado, contam-se
     * minutos corridos.
     */
    public static function nextContactDeadline(int $minutes, ?int $fromTs = null): string
    {
        $fromTs ??= time();

        $targetTs = BusinessClock::enabled()
            ? BusinessClock::deadlineFrom($fromTs, $minutes)
            : $fromTs + $minutes * 60;

        return gmdate('Y-m-d H:i:s', $targetTs);
    }

    /** Data guardada (UTC) na hora de Portugal, para as mensagens da Timeline. */
    private static function formatLocal(string $utc): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))
            ->setTimezone(\app_timezone())
            ->format('d/m/Y H:i');
    }

    /**
     * Agenda a Nova Data de Contacto (ex.: imobilizado que só se volta a
     * contactar daqui a X dias). Fica visível na Caixa de Entrada do
     * responsável, para saber quando a data vence.
     */
    public function setNextContact(int $processId, ?string $date, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);

        if (!self::allowsNextContact($process)) {
            throw new RuntimeException('A Nova Data de Contacto só está disponível para a combinação de prioridade e assunto definida em Configurações.');
        }

        // Limpar a data é sempre permitido.
        if ($date === null || trim($date) === '') {
            $this->processes->setNextContactAt($processId, null, $userId);
            $this->timeline->record($processId, 'NEXT_CONTACT_CLEARED', 'Nova Data de Contacto removida', null, $userId);

            return;
        }

        // O calendário está inibido na ficha do processo quando a Prioridade
        // agenda sozinha; o servidor recusa pelo mesmo motivo, para um pedido
        // fabricado não contornar a regra.
        if (self::autoNextContactMinutes($process) > 0) {
            throw new RuntimeException('Esta prioridade agenda o próximo contacto automaticamente — para escolher a data à mão, limpe o campo "Próx. Contacto Cliente" da prioridade em Configurações.');
        }

        // O formulário (datetime-local) envia hora local; guarda-se em UTC
        // como o resto do sistema.
        try {
            $local = new \DateTimeImmutable(trim($date), \app_timezone());
        } catch (\Exception) {
            throw new RuntimeException('Data inválida.');
        }

        if ($local < new \DateTimeImmutable('now', $local->getTimezone())) {
            throw new RuntimeException('A Nova Data de Contacto não pode ser no passado.');
        }

        $utc = $local->setTimezone(new \DateTimeZone('UTC'));
        $this->processes->setNextContactAt($processId, $utc->format('Y-m-d H:i:s'), $userId);
        $this->timeline->record(
            $processId,
            'NEXT_CONTACT_SET',
            'Novo contacto agendado para ' . $local->format('d/m/Y H:i'),
            null,
            $userId
        );
    }

    /**
     * Transfere o processo para outra Filial/Departamento (ex.: foi criado
     * na filial errada). Volta à fila do departamento de destino, sem
     * responsável, e os elementos de lá são notificados como numa nova lead.
     *
     * O Supervisor de Departamento só transfere processos do SEU departamento
     * ($allowedBatchIds); Admin/Supervisor transferem qualquer um.
     */
    public function transfer(int $processId, int $targetBatchId, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);

        if (in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)) {
            throw new RuntimeException('Não é possível transferir um processo concluído. Reabra-o primeiro.');
        }

        if ($targetBatchId === (int) $process['batch_id']) {
            throw new RuntimeException('O processo já pertence a essa Filial/Departamento.');
        }

        $target = (new \App\Modules\Administration\Repositories\BatchRepository())->findActiveById($targetBatchId);
        if ($target === null) {
            throw new RuntimeException('A Filial/Departamento de destino não existe ou está inativa.');
        }

        $queueStatusId = $this->processes->statusIdByCode('QUEUE');
        $this->processes->transferToBatch($processId, $targetBatchId, $queueStatusId, $userId);

        $destino = trim(($target['description'] ?? '') !== '' ? (string) $target['description'] : ('Lote #' . $targetBatchId));
        $this->timeline->record(
            $processId,
            'PROCESS_TRANSFERRED',
            "Processo transferido para outra Filial/Departamento ({$destino})",
            null,
            $userId
        );

        // A equipa de destino é avisada como numa nova lead (#6).
        $this->notifications->notifyBatchUsers(
            $targetBatchId,
            "📥 Processo transferido para o seu departamento: {$process['process_number']}",
            'Um processo foi transferido para a vossa fila. Abram a Fila Inteligente™ para o assumir.',
            'INFO',
            $userId
        );
    }

    /**
     * Devolve o processo à Fila Inteligente™ — o operador não está
     * disponível para o tratar e outro pode assumi-lo.
     */
    public function returnToQueue(int $processId, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);
        $this->guardTransition($process['status_code'], 'QUEUE');

        $queueStatusId = $this->processes->statusIdByCode('QUEUE');
        $this->processes->releaseToQueue($processId, $queueStatusId, $userId);

        $this->timeline->record($processId, 'PROCESS_RELEASED', 'Processo devolvido à fila', null, $userId);
    }

    /**
     * Reatribui o processo a outro operador (Admin/Supervisor) — o novo
     * responsável fica com o processo como se o tivesse assumido.
     */
    /**
     * @param array<int>|null $allowedBatchIds Quando não é null, só se pode
     *   reatribuir processos destes lotes. É o caso do Supervisor de
     *   Departamento: vê a Filial toda, mas só mexe no seu departamento.
     */
    public function reassign(int $processId, int $newUserId, int $actingUserId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        if ($allowedBatchIds !== null && !in_array((int) $process['batch_id'], $allowedBatchIds, true)) {
            throw new RuntimeException('Este processo é de outro departamento; só o pode consultar, não reatribuir.');
        }

        if (in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)) {
            throw new RuntimeException('Não é possível reatribuir um processo concluído. Reabra-o primeiro.');
        }

        $newUser = $this->users->findById($newUserId);
        if ($newUser === null || !(bool) $newUser['active']) {
            throw new RuntimeException('O utilizador escolhido não existe ou está inativo.');
        }

        $assignedStatusId = $this->processes->statusIdByCode('ASSIGNED');
        $this->processes->reassign($processId, $newUserId, $assignedStatusId, $actingUserId);

        $this->timeline->record(
            $processId,
            'PROCESS_REASSIGNED',
            'Processo reatribuído a ' . $newUser['first_name'] . ' ' . $newUser['last_name'],
            null,
            $actingUserId
        );
    }

    /**
     * RF-0014 / RN-0040 a RN-0044 - o SLA e os tempos são sempre calculados
     * a partir dos timestamps, nunca armazenados (decisão arquitetural §10.20).
     * V1 conclui diretamente para "Resolvido" (SOLVED); o arquivamento para
     * "Encerrado" (CLOSED) fica para o módulo de Administração/Relatórios.
     */
    public function close(int $processId, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);
        $this->guardTransition($process['status_code'], 'SOLVED');

        $solvedStatusId = $this->processes->statusIdByCode('SOLVED');
        $this->processes->close($processId, $solvedStatusId, $userId);

        // Um processo concluído já não precisa de lembrete de próximo
        // contacto — sem isto, a data que ficou marcada antes de fechar
        // continuava a aparecer na ficha e nas listagens, e o job agendado
        // ainda a considerava (mesmo já filtrando por estado aberto).
        if (!empty($process['next_contact_at'])) {
            $this->processes->setNextContactAt($processId, null, $userId);
        }

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
    public function reopen(int $processId, int $userId, ?array $allowedBatchIds = null): void
    {
        $process = $this->processes->findById($processId);
        if ($process === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $this->guardDepartment($process, $allowedBatchIds);
        $this->guardTransition($process['status_code'], 'QUEUE');

        $queueStatusId = $this->processes->statusIdByCode('QUEUE');
        $this->processes->reopen($processId, $queueStatusId, $userId);

        $this->timeline->record($processId, 'PROCESS_REOPENED', 'Processo reaberto', null, $userId);
    }

    /**
     * Isolamento por departamento nas ações que MEXEM no processo (assumir,
     * concluir, reabrir, pôr em espera, devolver à fila, agendar contacto,
     * reatribuir). Quem só tem visibilidade alargada — como o Supervisor de
     * Departamento, que vê a Filial toda — pode consultar tudo, mas só age
     * no seu departamento.
     *
     * $allowedBatchIds null = sem restrição (Admin/Supervisor).
     *
     * @param array<string, mixed> $process
     * @param array<int>|null $allowedBatchIds
     */
    private function guardDepartment(array $process, ?array $allowedBatchIds): void
    {
        if ($allowedBatchIds === null) {
            return;
        }

        if (!in_array((int) $process['batch_id'], $allowedBatchIds, true)) {
            throw new RuntimeException('Este processo é de outro departamento; só o pode consultar.');
        }
    }

    private function guardTransition(string $currentStatusCode, string $targetStatusCode): void
    {
        $waiting = $this->waitingCodes();

        // Os Motivos de Pausa do SLA são configuráveis, por isso não estão no
        // mapa fixo TRANSITIONS: entram e saem sempre da mesma forma, seja
        // qual for o código que o Administrador lhes tenha dado.
        if (in_array($targetStatusCode, $waiting, true) && in_array($currentStatusCode, ['ASSIGNED', 'IN_PROGRESS'], true)) {
            return;
        }

        if (in_array($currentStatusCode, $waiting, true) && in_array($targetStatusCode, ['IN_PROGRESS', 'SOLVED', 'QUEUE'], true)) {
            return;
        }

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
