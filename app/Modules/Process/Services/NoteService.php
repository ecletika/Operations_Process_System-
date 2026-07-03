<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Modules\Process\Repositories\NoteRepository;
use RuntimeException;

/**
 * OPS-PRD-001 §7.10 (RN-0033/0034) e RF-0023/0024.
 * "Após os 10 minutos, a observação não será alterada. O sistema cria
 * automaticamente uma versão corrigida, preservando a original."
 */
final class NoteService
{
    private const EDIT_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly NoteRepository $notes = new NoteRepository(),
        private readonly TimelineService $timeline = new TimelineService(),
    ) {
    }

    public function add(int $processId, string $text, int $authorId): void
    {
        $this->notes->create($processId, $text, $authorId);
        $this->timeline->record($processId, 'NOTE_ADDED', 'Observação adicionada', $text, $authorId);
    }

    /**
     * Dentro da janela de 10 minutos e do mesmo autor: edita no próprio registo.
     * Fora da janela: preserva o original e cria uma nova versão (RN-0034).
     */
    public function edit(int $noteId, string $newText, int $authorId): void
    {
        $note = $this->notes->findById($noteId);

        if ($note === null) {
            throw new RuntimeException('Observação não encontrada.');
        }

        if ((int) $note['author_id'] !== $authorId) {
            throw new RuntimeException('Só o autor pode editar esta observação.');
        }

        $rootId = $note['edited_from'] !== null ? (int) $note['edited_from'] : (int) $note['id'];
        $ageMinutes = (time() - strtotime((string) $note['created_at'])) / 60;

        if ($ageMinutes <= self::EDIT_WINDOW_MINUTES) {
            $this->notes->updateText((int) $note['id'], $newText);
        } else {
            $this->notes->create((int) $note['process_id'], $newText, $authorId, $rootId);
        }

        $this->timeline->record((int) $note['process_id'], 'NOTE_ADDED', 'Observação atualizada', $newText, $authorId);
    }

    public function listByProcess(int $processId): array
    {
        return $this->notes->listByProcess($processId);
    }
}
