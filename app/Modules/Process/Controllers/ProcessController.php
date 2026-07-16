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
        // Isolamento por departamento (RN-0011): o operador só vê os processos
        // dos seus lotes; Supervisor/Admin (ou "ver todos os lotes") veem tudo.
        $processes = (new ProcessRepository())->listQueue($this->allowedBatchIds());

        $this->view('Process/Views/queue', [
            'processes' => $processes,
        ]);
    }

    /**
     * Lotes que o utilizador atual pode ver/assumir na Fila Inteligente™.
     * Devolve null quando pode ver tudo (Supervisor/Admin com process.view_all,
     * ou operador com "ver todos os lotes"); caso contrário, a lista dos lotes
     * do seu departamento. Cai para o lote principal em sessões antigas que
     * ainda não tenham a lista completa.
     *
     * @return array<int>|null
     */
    private function allowedBatchIds(): ?array
    {
        // Regra fixa (RN-0011): só as chefias (process.view_all → Admin/
        // Supervisor) veem e assumem processos fora do seu departamento.
        // O "view_all_batches" da ficha do utilizador NÃO abre exceção aqui:
        // serve apenas para o operador poder CRIAR um processo noutro
        // departamento (ex.: a Receção abre um pedido para a Oficina).
        // Para dar a um operador visão de outro departamento, atribua-lhe o
        // lote desse departamento.
        if (in_array('process.view_all', (array) Session::get('permissions', []), true)) {
            return null;
        }

        $allowed = Session::get('allowed_batch_ids');
        if ($allowed === null) {
            // Sessão iniciada antes desta versão: usa o lote principal.
            $single = Session::get('batch_id');

            return $single !== null ? [(int) $single] : [];
        }

        return array_map('intval', (array) $allowed);
    }

    /**
     * Meus Processos (§3.9).
     */
    public function mine(Request $request): never
    {
        $userId = (int) Session::get('user_id');
        $repository = new ProcessRepository();
        $archived = (string) $request->input('view', '') === 'archived';

        $this->view('Process/Views/mine', [
            'processes' => $repository->listAssignedTo($userId, $archived),
            'createdProcesses' => $repository->listCreatedBy($userId, $archived),
            'userId' => $userId,
            'archived' => $archived,
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
        $filters = $this->buildAllFilters($request);

        $this->view('Process/Views/all', [
            'processes' => (new ProcessRepository())->filterAll($filters),
            'tab' => $filters['tab'],
            'filters' => $filters,
            'statuses' => (new StatusRepository())->listAll(),
            'batches' => (new BatchRepository())->listAll(),
            'priorities' => (new PriorityRepository())->listActive(),
            'subjects' => (new SubjectRepository())->listActive(),
            'users' => (new UserRepository())->listAll(),
        ]);
    }

    /**
     * Filtros de "Todos os Processos". Os campos estado/lote/prioridade/
     * assunto/responsável aceitam multi-seleção (arrays de ids); a aba e as
     * datas são escalares. Partilhado entre a página e a exportação Excel.
     *
     * @return array<string, mixed>
     */
    private function buildAllFilters(Request $request): array
    {
        $tab = (string) $request->input('tab', 'in_progress');
        $validTabs = ['in_progress', 'em_tratamento', 'em_espera', 'resolvidos', 'encerrados', 'reabertos', 'arquivados', 'no_interaction', 'all'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'in_progress';
        }

        $multi = static fn (mixed $v): array => array_values(array_filter(
            array_map('intval', (array) $v),
            static fn (int $x): bool => $x > 0
        ));

        return [
            'tab' => $tab,
            'status_id' => $multi($request->input('status_id', [])),
            'batch_id' => $multi($request->input('batch_id', [])),
            'priority_id' => $multi($request->input('priority_id', [])),
            'subject_id' => $multi($request->input('subject_id', [])),
            'assigned_to' => $multi($request->input('assigned_to', [])),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];
    }

    /**
     * Exporta a lista "Todos os Processos" para Excel, respeitando os mesmos
     * filtros aplicados na página (RF-0041).
     */
    public function allExcel(Request $request): never
    {
        $filters = $this->buildAllFilters($request);
        $processes = (new ProcessRepository())->filterAll($filters, 5000);

        $rows = array_map(static fn (array $p): array => [
            'Nº Processo' => $p['process_number'],
            'Filial' => $p['branch_name'] ?? '',
            'Departamento' => $p['department_name'] ?? '',
            'Cliente' => $p['customer_name'],
            'Matrícula' => $p['vehicle_plate'],
            'Assunto' => $p['subject_name'],
            'Estado' => $p['status_name'],
            'Prioridade' => $p['priority_name'],
            'Responsável' => trim(($p['assigned_first_name'] ?? '') . ' ' . ($p['assigned_last_name'] ?? '')),
            'Criado por' => trim(($p['creator_first_name'] ?? '') . ' ' . ($p['creator_last_name'] ?? '')),
            'Contactos' => (int) $p['contact_count'],
            'Reaberturas' => (int) $p['reopen_count'],
            'Criado em' => $p['created_at'],
            'Concluído em' => $p['closed_at'] ?? '',
        ], $processes);

        $html = (new \App\Modules\Reports\Services\ExcelExportService())->render($rows, 'Todos os Processos');

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="todos_os_processos_' . date('Y-m-d_His') . '.xls"');
        echo $html;
        exit;
    }

    /**
     * Lookup de matrícula (RF-0037): ao escrever a matrícula no Novo Processo,
     * devolve o cliente associado (se a viatura já existir) para preencher o
     * nome automaticamente. Responde JSON.
     */
    public function vehicleLookup(Request $request): never
    {
        $plate = trim((string) $request->input('plate', ''));
        if ($plate === '') {
            Response::json(['found' => false]);
        }

        $vehicle = (new \App\Modules\Process\Repositories\VehicleRepository())->findByPlate($plate);
        if ($vehicle === null) {
            Response::json(['found' => false]);
        }

        $customer = (new \App\Modules\Process\Repositories\CustomerRepository())->findById((int) $vehicle['customer_id']);
        if ($customer === null) {
            Response::json(['found' => false]);
        }

        Response::json([
            'found' => true,
            'customer_name' => $customer['name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
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
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->assume($id, $userId, $allowed));
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

        $allowed = $this->allowedBatchIds();
        $queue = (new ProcessRepository())->listQueue($allowed);

        if ($queue === []) {
            Session::flash('success', 'Fila vazia — não há nenhum processo à espera. Bom trabalho!');
            Response::redirect('/processes/queue');
        }

        $processId = (int) $queue[0]['id'];
        $userId = (int) Session::get('user_id');

        try {
            (new ProcessService())->assume($processId, $userId, $allowed);
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

    /** Devolve o processo à fila (o operador não está disponível). */
    public function release(Request $request, array $params): never
    {
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->returnToQueue($id, $userId));
    }

    /** Reatribui o processo a outro operador (Admin/Supervisor). */
    public function reassign(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/all');
        }

        try {
            (new ProcessService())->reassign($processId, (int) $request->input('user_id', 0), (int) Session::get('user_id'));
            Session::flash('success', 'Processo reatribuído.');
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        Response::redirect((string) ($request->input('back') ?: '/processes/all'));
    }

    /** Arquiva ou desarquiva um processo (concluído). */
    public function archive(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/' . $processId);
        }

        $archive = $request->input('archive', '1') === '1';
        (new ProcessRepository())->setArchived($processId, $archive, (int) Session::get('user_id'));

        Session::flash('success', $archive ? 'Processo arquivado.' : 'Processo desarquivado.');
        Response::redirect('/processes/' . $processId);
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
        $batches = $canChooseBatch ? (new BatchRepository())->listAll() : [];
        $sessionBatchId = (int) Session::get('batch_id');
        $subjectRepo = new SubjectRepository();

        // Departamento inicial: se o utilizador escolhe filial, vem do lote
        // selecionado; caso contrário, é o departamento do próprio (sessão).
        $selectedBatchId = (int) ($old['batch_id'] ?? $sessionBatchId);
        $initialDeptId = (int) Session::get('department_id') ?: null;
        foreach ($batches as $batch) {
            if ((int) $batch['id'] === $selectedBatchId) {
                $initialDeptId = (int) $batch['department_id'];
                break;
            }
        }

        // Mapa departamento → assuntos permitidos (#5), para o JS trocar a
        // lista de Assuntos quando muda a Filial/Departamento sem recarregar.
        $subjectsByDept = [];
        foreach ($batches as $batch) {
            $deptId = (int) $batch['department_id'];
            if (!isset($subjectsByDept[$deptId])) {
                $subjectsByDept[$deptId] = array_map(
                    static fn (array $s): array => ['id' => (int) $s['id'], 'name' => $s['name']],
                    $subjectRepo->listActiveForDepartment($deptId)
                );
            }
        }

        $this->view('Process/Views/create', [
            'errors' => $errors,
            'old' => $old,
            'reopenCandidate' => $reopenCandidate,
            'subjects' => $subjectRepo->listActiveForDepartment($initialDeptId),
            'priorities' => (new PriorityRepository())->listActive(),
            'canChooseBatch' => $canChooseBatch,
            'batches' => $batches,
            'sessionBatchId' => $sessionBatchId,
            'subjectsByDept' => $subjectsByDept,
        ]);
    }

    private function canChooseBatch(): bool
    {
        $permissions = (array) Session::get('permissions', []);

        return (bool) Session::get('view_all_batches', false)
            || in_array('process.view_all', $permissions, true);
    }
}
