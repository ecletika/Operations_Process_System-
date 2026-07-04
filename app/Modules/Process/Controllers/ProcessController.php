<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\BatchRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Process\DTO\CreateProcessDTO;
use App\Modules\Process\Repositories\EventRepository;
use App\Modules\Process\Repositories\PriorityRepository;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Repositories\StatusRepository;
use App\Modules\Process\Repositories\SubjectRepository;
use App\Modules\Process\Repositories\TimelineRepository;
use App\Modules\Process\Services\AttachmentService;
use App\Modules\Process\Services\InteractionService;
use App\Modules\Process\Services\NoteService;
use App\Modules\Process\Services\ProcessService;
use App\Modules\Process\Services\ReplayService;
use App\Modules\Intelligence\Services\IntelligenceService;
use App\Modules\Process\Validators\CreateProcessValidator;
use RuntimeException;

final class ProcessController extends Controller
{
    /**
     * Fila Inteligente™ (RF-0009, RN-0009 a RN-0011).
     */
    public function queue(Request $request): never
    {
        // Operador vê só o seu lote, exceto se o Admin lhe deu visibilidade
        // total (view_all_batches, definido na ficha do utilizador).
        $isOperator = Session::get('role_code') === 'ROLE_OPERATOR';
        $viewAllBatches = (bool) Session::get('view_all_batches', false);
        $batchId = ($isOperator && !$viewAllBatches) ? Session::get('batch_id') : null;

        $processes = (new ProcessRepository())->listQueue($batchId);

        $this->view('Process/Views/queue', [
            'processes' => $processes,
        ]);
    }

    /**
     * Meus Processos (§3.9).
     */
    public function mine(Request $request): never
    {
        $processes = (new ProcessRepository())->listAssignedTo((int) Session::get('user_id'));

        $this->view('Process/Views/mine', [
            'processes' => $processes,
        ]);
    }

    /**
     * "Todos os Processos" - visão do Admin/Supervisor (permissão
     * process.view_all): abas (Em Andamento / Concluídos / Sem Interação /
     * Todos) + filtros combináveis (estado, lote, prioridade, assunto,
     * responsável, intervalo de datas).
     */
    public function all(Request $request): never
    {
        $tab = (string) $request->input('tab', 'in_progress');
        $validTabs = ['in_progress', 'em_tratamento', 'em_espera', 'resolvidos', 'encerrados', 'reabertos', 'no_interaction', 'all'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'in_progress';
        }

        $filters = [
            'tab' => $tab,
            'status_id' => $request->input('status_id', ''),
            'batch_id' => $request->input('batch_id', ''),
            'priority_id' => $request->input('priority_id', ''),
            'subject_id' => $request->input('subject_id', ''),
            'assigned_to' => $request->input('assigned_to', ''),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
        ];

        $this->view('Process/Views/all', [
            'processes' => (new ProcessRepository())->filterAll($filters),
            'tab' => $tab,
            'filters' => $filters,
            'statuses' => (new StatusRepository())->listAll(),
            'batches' => (new BatchRepository())->listAll(),
            'priorities' => (new PriorityRepository())->listActive(),
            'subjects' => (new SubjectRepository())->listActive(),
            'users' => (new UserRepository())->listAll(),
        ]);
    }

    public function create(Request $request): never
    {
        $this->renderCreateForm(
            errors: Session::pullFlash('errors', []),
            old: Session::pullFlash('old', []),
            reopenCandidate: Session::pullFlash('reopen_candidate')
        );
    }

    /**
     * RF-0009 / RN-0017 a RN-0024 - cria o processo, deteta duplicidade e
     * aplica a Janela de Reincidência.
     */
    public function store(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/create');
        }

        $dto = CreateProcessDTO::fromArray($request->all());
        $errors = (new CreateProcessValidator())->validate($dto);

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            Response::redirect('/processes/create');
        }

        $userId = (int) Session::get('user_id');
        $companyId = (int) Session::get('company_id');
        $batchId = Session::get('batch_id');

        // Admin/Supervisor (ou quem tem visibilidade total) pode escolher a
        // Filial/Lote de destino; validamos que o lote submetido existe.
        if ($this->canChooseBatch()) {
            $chosen = (int) $request->input('batch_id', 0);
            if ($chosen > 0 && (new BatchRepository())->findActiveById($chosen) !== null) {
                $batchId = $chosen;
            }
        }

        if ($batchId === null) {
            Session::flash('errors', ['O seu utilizador não está associado a nenhum lote. Contacte o Administrador.']);
            Response::redirect('/processes/create');
        }

        try {
            $result = (new ProcessService())->create($dto, $userId, $companyId, (int) $batchId);
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Session::flash('old', $request->all());
            Response::redirect('/processes/create');
        }

        if ($result->status === 'needs_reopen_decision') {
            Session::flash('old', $request->all());
            Session::flash('reopen_candidate', [
                'process_id' => $result->processId,
                'process_number' => $result->processNumber,
            ]);
            Response::redirect('/processes/create');
        }

        if ($result->status === 'duplicate_interaction_added') {
            Session::flash('success', "Já existia um processo aberto ({$result->processNumber}). Foi adicionada uma nova interação.");
        } else {
            Session::flash('success', "Processo {$result->processNumber} criado com sucesso.");
        }

        Response::redirect('/processes/' . $result->processId);
    }

    public function show(Request $request, array $params): never
    {
        $processId = (int) $params['id'];
        $repository = new ProcessRepository();
        $process = $repository->findById($processId);

        if ($process === null) {
            http_response_code(404);
            echo 'Processo não encontrado.';
            exit;
        }

        $timeline = (new TimelineRepository())->listByProcess($processId);
        $interactions = (new InteractionService())->listByProcess($processId);
        $eventsTotal = count((new EventRepository())->listByProcess($processId));
        $notes = (new NoteService())->listByProcess($processId);
        $attachments = (new AttachmentService())->listByProcess($processId);

        $dna = (new ProcessService())->calculateDna($process, $eventsTotal);

        $intelligence = new IntelligenceService();

        $this->view('Process/Views/show', [
            'process' => $process,
            'timeline' => $timeline,
            'interactions' => $interactions,
            'notes' => $notes,
            'attachments' => $attachments,
            'dna' => $dna,
            'currentUserId' => (int) Session::get('user_id'),
            // RN-0059/0060 - sinalização em tempo real, sem guardar estado.
            'isFrequentCustomer' => $intelligence->isFrequentCustomer((int) $process['customer_id']),
            'isRecurrentVehicle' => $intelligence->isRecurrentVehicle((int) $process['vehicle_id']),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    /**
     * Operations Replay™ - reproduz visualmente a história do processo
     * (OPS-UI-001 §24, proposta nova do PRD).
     */
    public function replay(Request $request, array $params): never
    {
        $processId = (int) $params['id'];
        $process = (new ProcessRepository())->findById($processId);

        if ($process === null) {
            http_response_code(404);
            echo 'Processo não encontrado.';
            exit;
        }

        $this->view('Process/Views/replay', [
            'process' => $process,
            'steps' => (new ReplayService())->stepsFor($processId),
        ]);
    }

    public function assume(Request $request, array $params): never
    {
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->assume($id, $userId));
    }

    /**
     * Próximo Processo (RN-0011): assume automaticamente o processo mais
     * prioritário da fila do operador e abre-o — um clique, zero escolhas.
     */
    public function next(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/queue');
        }

        $isOperator = Session::get('role_code') === 'ROLE_OPERATOR';
        $viewAllBatches = (bool) Session::get('view_all_batches', false);
        $batchId = ($isOperator && !$viewAllBatches) ? Session::get('batch_id') : null;

        $queue = (new ProcessRepository())->listQueue($batchId !== null ? (int) $batchId : null);

        if ($queue === []) {
            Session::flash('success', 'Fila vazia — não há nenhum processo à espera. Bom trabalho!');
            Response::redirect('/processes/queue');
        }

        $processId = (int) $queue[0]['id'];
        $userId = (int) Session::get('user_id');

        try {
            (new ProcessService())->assume($processId, $userId);
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Response::redirect('/processes/queue');
        }

        Session::flash('success', 'Processo assumido automaticamente (o mais prioritário da fila).');
        Response::redirect('/processes/' . $processId);
    }

    public function close(Request $request, array $params): never
    {
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->close($id, $userId));
    }

    public function reopen(Request $request, array $params): never
    {
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->reopen($id, $userId));
    }

    /**
     * Excluir Processo - restrito à permissão process.delete (só Admin,
     * verificado na rota). Soft delete: sai de todas as listagens mas
     * o histórico fica preservado na base de dados.
     */
    public function destroy(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/' . $processId);
        }

        try {
            (new ProcessService())->delete($processId, (int) Session::get('user_id'));
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Response::redirect('/processes/' . $processId);
        }

        Session::flash('success', 'Processo excluído.');
        Response::redirect('/processes/all');
    }

    private function runAction(Request $request, array $params, callable $action): never
    {
        $processId = (int) $params['id'];
        $userId = (int) Session::get('user_id');

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/' . $processId);
        }

        try {
            $action(new ProcessService(), $processId, $userId);
            Session::flash('success', 'Ação executada com sucesso.');
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        Response::redirect('/processes/' . $processId);
    }

    private function renderCreateForm(array $errors, array $old, ?array $reopenCandidate): never
    {
        // Quem pode ver várias filiais (Admin/Supervisor via process.view_all,
        // ou operador com view_all_batches) escolhe em que Filial/Lote o
        // processo entra; caso contrário entra sempre no lote do próprio.
        $canChooseBatch = $this->canChooseBatch();

        $this->view('Process/Views/create', [
            'errors' => $errors,
            'old' => $old,
            'reopenCandidate' => $reopenCandidate,
            'subjects' => (new SubjectRepository())->listActive(),
            'priorities' => (new PriorityRepository())->listActive(),
            'canChooseBatch' => $canChooseBatch,
            'batches' => $canChooseBatch ? (new BatchRepository())->listAll() : [],
            'sessionBatchId' => (int) Session::get('batch_id'),
        ]);
    }

    private function canChooseBatch(): bool
    {
        $permissions = (array) Session::get('permissions', []);

        return (bool) Session::get('view_all_batches', false)
            || in_array('process.view_all', $permissions, true);
    }
}
