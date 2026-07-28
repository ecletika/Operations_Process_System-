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
    /**
     * Departamentos que o utilizador pode VER em "Todos os Processos".
     * null = sem limite (Admin/Supervisor, com process.view_all).
     *
     * O Supervisor de Departamento vê conforme o âmbito escolhido na sua
     * ficha: toda a Filial ou só os departamentos marcados. Isto é só sobre
     * VER — assumir/reatribuir continua limitado ao seu lote (allowedBatchIds).
     *
     * @return array<int>|null
     */
    private function viewScopeDepartmentIds(): ?array
    {
        $permissions = (array) Session::get('permissions', []);

        if (in_array('process.view_all', $permissions, true)) {
            return null;
        }

        if ((string) Session::get('view_scope', 'OWN') === 'OWN') {
            $department = Session::get('department_id');

            return $department !== null ? [(int) $department] : [];
        }

        return array_map('intval', (array) Session::get('viewable_department_ids', []));
    }

    /** @see \App\Modules\Process\Support\BatchScope regra única de âmbito */
    private function ownBatchIds(): array
    {
        return \App\Modules\Process\Support\BatchScope::own();
    }

    private function allowedBatchIds(): ?array
    {
        return \App\Modules\Process\Support\BatchScope::allowed();
    }

    /**
     * Meus Processos (§3.9).
     */
    public function mine(Request $request): never
    {
        $userId = (int) Session::get('user_id');
        $repository = new ProcessRepository();
        $view = (string) $request->input('view', '');
        $archived = $view === 'archived';
        $imobilizados = $view === 'imobilizados';
        // A aba Imobilizados nunca mistura com a Arquivada: mostra sempre os
        // processos em curso, para facilitar o controlo dos imobilizados.
        $subjectCode = $imobilizados ? 'IMO' : null;
        // Imobilizados sai do "Em curso" — só aparece na aba própria.
        $excludeSubjectCode = (!$archived && !$imobilizados) ? 'IMO' : null;

        $this->view('Process/Views/mine', [
            'processes' => $repository->listAssignedTo($userId, $imobilizados ? false : $archived, $subjectCode, $excludeSubjectCode),
            'createdProcesses' => $repository->listCreatedBy($userId, $imobilizados ? false : $archived, $subjectCode, $excludeSubjectCode),
            'userId' => $userId,
            'archived' => $archived,
            'imobilizados' => $imobilizados,
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
            // Lotes em que pode agir (reatribuir). null = todos (Admin/Supervisor);
            // o Supervisor de Departamento só age no seu.
            'actionableBatchIds' => $this->allowedBatchIds(),
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
            // Âmbito de visibilidade — não é um filtro que o utilizador
            // escolhe: é o limite do que ele pode ver (Supervisor de Depto.).
            'scope_department_ids' => $this->viewScopeDepartmentIds(),
            'q' => trim((string) $request->input('q', '')),
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

        // As datas são guardadas em UTC; no ficheiro têm de sair na mesma hora
        // que aparece no ecrã (Europa/Lisboa), senão a exportação mostra menos
        // uma hora. Vazio continua vazio (não "—", que sujaria a folha).
        $hora = static function (?string $utc): string {
            if ($utc === null || trim($utc) === '') {
                return '';
            }

            try {
                return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))
                    ->setTimezone(app_timezone())
                    ->format('Y-m-d H:i');
            } catch (\Exception) {
                return $utc;
            }
        };

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
            // Mesmas colunas do ecrã "Todos os Processos". No Excel o "Criado
            // por" fica em coluna própria (numa folha de cálculo não dá para o
            // pôr como segunda linha, e é útil para filtrar/ordenar).
            'Criado em' => $hora($p['created_at']),
            'Criado por' => trim(($p['creator_first_name'] ?? '') . ' ' . ($p['creator_last_name'] ?? '')),
            'Último Contacto' => $hora($p['last_contact_at'] ?? null),
            'Próximo Contacto' => $hora($p['next_contact_at'] ?? null),
            'Reaberturas' => (int) $p['reopen_count'],
            'Concluído em' => $hora($p['closed_at'] ?? null),
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

        // Só se mexe em processos do próprio departamento. Quem tem visão
        // alargada (Supervisor de Departamento) consulta os outros, mas os
        // botões de ação não aparecem.
        $allowed = $this->allowedBatchIds();
        $canAct = $allowed === null || in_array((int) $process['batch_id'], $allowed, true);

        // Transferir de Filial/Departamento: só quem tem process.transfer, e o
        // Supervisor de Departamento só nos processos do seu (canAct).
        $canTransfer = $canAct
            && in_array('process.transfer', (array) Session::get('permissions', []), true)
            && !in_array($process['status_code'], ['SOLVED', 'CLOSED'], true);
        $transferTargets = $canTransfer ? (new BatchRepository())->listAll() : [];

        $this->view('Process/Views/show', [
            'process' => $process,
            'canAct' => $canAct,
            'canTransfer' => $canTransfer,
            'transferTargets' => $transferTargets,
            // Observações/anexos: o criador contribui mesmo noutro departamento.
            'canContribute' => \App\Modules\Process\Support\BatchScope::canContribute($process),
            // Motivos de Pausa do SLA configurados (só os ativos são oferecidos).
            'pauseReasons' => (new StatusRepository())->listWaiting(onlyActive: true),
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

        // "Próximo Processo" = "dá-me o MEU próximo trabalho": sai sempre da
        // fila do próprio departamento, mesmo para Admin/Supervisor. Sem isto,
        // uma chefia carregava no botão e apanhava o processo mais prioritário
        // de qualquer filial/departamento — que não é o trabalho dela.
        // Para assumir um processo de outro departamento, a chefia abre-o e
        // usa o "Assumir" desse processo.
        $own = $this->ownBatchIds();
        $queue = (new ProcessRepository())->listQueue($own);

        if ($queue === []) {
            Session::flash('success', 'Fila vazia — não há nenhum processo à espera no seu departamento. Bom trabalho!');
            Response::redirect('/processes/queue');
        }

        $processId = (int) $queue[0]['id'];
        $userId = (int) Session::get('user_id');

        try {
            (new ProcessService())->assume($processId, $userId, $own);
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Response::redirect('/processes/queue');
        }

        Session::flash('success', 'Processo assumido automaticamente (o mais prioritário da fila).');
        Response::redirect('/processes/' . $processId);
    }

    /**
     * Mudar o estado: pôr em espera (Aguarda Cliente/Peças/Oficina/Terceiros,
     * que param o relógio do SLA) ou retomar o tratamento.
     */
    public function changeStatus(Request $request, array $params): never
    {
        $status = (string) $request->input('status', '');
        $estadosValidos = array_merge((new ProcessService())->waitingCodes(), ['IN_PROGRESS']);

        if (!in_array($status, $estadosValidos, true)) {
            Session::flash('errors', ['Estado inválido.']);
            Response::redirect('/processes/' . (int) $params['id']);
        }

        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->changeStatus($id, $status, $userId, $allowed));
    }

    /** Agenda a Nova Data de Contacto (Imobilizados/Baixa, por omissão). */
    public function nextContact(Request $request, array $params): never
    {
        $date = (string) $request->input('next_contact_at', '');
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->setNextContact($id, $date, $userId, $allowed));
    }

    public function close(Request $request, array $params): never
    {
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->close($id, $userId, $allowed));
    }

    public function reopen(Request $request, array $params): never
    {
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->reopen($id, $userId, $allowed));
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

    /** Transfere o processo para outra Filial/Departamento (criado no sítio errado). */
    public function transfer(Request $request, array $params): never
    {
        $targetBatchId = (int) $request->input('batch_id', 0);
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->transfer($id, $targetBatchId, $userId, $allowed));
    }

    /** Devolve o processo à fila (o operador não está disponível). */
    public function release(Request $request, array $params): never
    {
        $allowed = $this->allowedBatchIds();
        $this->runAction($request, $params, fn (ProcessService $service, int $id, int $userId) => $service->returnToQueue($id, $userId, $allowed));
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
            (new ProcessService())->reassign(
                $processId,
                (int) $request->input('user_id', 0),
                (int) Session::get('user_id'),
                $this->allowedBatchIds()
            );
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

        // Arquivar também é uma ação sobre o processo: mesma guarda de
        // departamento das restantes (concluir, reabrir, etc.).
        $allowed = $this->allowedBatchIds();
        $process = (new ProcessRepository())->findById($processId);
        if ($process === null || ($allowed !== null && !in_array((int) $process['batch_id'], $allowed, true))) {
            Session::flash('errors', ['Este processo é de outro departamento; só o pode consultar.']);
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
