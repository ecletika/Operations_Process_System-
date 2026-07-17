<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Core\ApiContext;
use App\Core\Request;
use App\Modules\Process\DTO\CreateProcessDTO;
use App\Modules\Process\Repositories\EventRepository;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Repositories\TimelineRepository;
use App\Modules\Process\Services\AttachmentService;
use App\Modules\Process\Services\InteractionService;
use App\Modules\Process\Services\NoteService;
use App\Modules\Process\Services\ProcessService;
use App\Modules\Process\Validators\CreateProcessValidator;
use RuntimeException;

/**
 * Fase 5 - API REST v1 do módulo Processo. Espelha o ProcessController web,
 * mas usa ApiContext (Bearer token) em vez de Session e devolve JSON.
 */
final class ApiProcessController extends ApiController
{
    public function queue(Request $request): never
    {
        $this->ok((new ProcessRepository())->listQueue($this->apiBatchScope()));
    }

    /**
     * Mesmo isolamento por departamento da web (RN-0011): só Admin/Supervisor
     * veem/assumem fora do seu lote. listQueue espera uma LISTA de lotes —
     * passar o int diretamente rebentava com TypeError desde essa mudança.
     *
     * @return array<int>|null
     */
    private function apiBatchScope(): ?array
    {
        if (in_array(ApiContext::roleCode(), ['ROLE_ADMIN', 'ROLE_SUPERVISOR'], true)) {
            return null;
        }

        $batchId = ApiContext::batchId();

        return $batchId !== null ? [(int) $batchId] : [];
    }

    public function mine(Request $request): never
    {
        $this->ok((new ProcessRepository())->listAssignedTo(ApiContext::userId()));
    }

    public function store(Request $request): never
    {
        $dto = CreateProcessDTO::fromArray($request->all());
        $errors = (new CreateProcessValidator())->validate($dto);

        if ($errors !== []) {
            $this->fail(implode(' ', $errors), 422);
        }

        $batchId = ApiContext::batchId();
        if ($batchId === null) {
            $this->fail('O utilizador não está associado a nenhum lote.', 422);
        }

        try {
            $result = (new ProcessService())->create($dto, ApiContext::userId(), ApiContext::companyId(), $batchId);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }

        $this->ok([
            'status' => $result->status,
            'process_id' => $result->processId,
            'process_number' => $result->processNumber,
        ], 201);
    }

    public function show(Request $request, array $params): never
    {
        $processId = (int) $params['id'];
        $process = (new ProcessRepository())->findById($processId);

        if ($process === null) {
            $this->fail('Processo não encontrado.', 404);
        }

        $eventsTotal = count((new EventRepository())->listByProcess($processId));

        $this->ok([
            'process' => $process,
            'dna' => (new ProcessService())->calculateDna($process, $eventsTotal),
            'timeline' => (new TimelineRepository())->listByProcess($processId),
            'interactions' => (new InteractionService())->listByProcess($processId),
            'notes' => (new NoteService())->listByProcess($processId),
            'attachments' => (new AttachmentService())->listByProcess($processId),
        ]);
    }

    public function assume(Request $request, array $params): never
    {
        $scope = $this->apiBatchScope();
        $this->runAction($params, fn (ProcessService $s, int $id) => $s->assume($id, ApiContext::userId(), $scope));
    }

    public function close(Request $request, array $params): never
    {
        $scope = $this->apiBatchScope();
        $this->runAction($params, fn (ProcessService $s, int $id) => $s->close($id, ApiContext::userId(), $scope));
    }

    public function reopen(Request $request, array $params): never
    {
        $scope = $this->apiBatchScope();
        $this->runAction($params, fn (ProcessService $s, int $id) => $s->reopen($id, ApiContext::userId(), $scope));
    }

    public function addNote(Request $request, array $params): never
    {
        $processId = (int) $params['id'];
        $text = trim((string) $request->input('note', ''));

        if ($text === '') {
            $this->fail('O campo "note" é obrigatório.', 422);
        }

        (new NoteService())->add($processId, $text, ApiContext::userId());

        $this->ok(['message' => 'Observação adicionada.'], 201);
    }

    public function addAttachment(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        try {
            (new AttachmentService())->upload($processId, $_FILES['file'] ?? [], ApiContext::userId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }

        $this->ok(['message' => 'Anexo enviado.'], 201);
    }

    private function runAction(array $params, callable $action): never
    {
        $processId = (int) $params['id'];

        try {
            $action(new ProcessService(), $processId);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }

        $this->ok(['message' => 'Ação executada com sucesso.']);
    }
}
