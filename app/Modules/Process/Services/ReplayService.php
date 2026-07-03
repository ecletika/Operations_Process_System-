<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Modules\Process\Repositories\TimelineRepository;

/**
 * Operations Replay™ (OPS-UI-001 §24 - proposta do PRD):
 * "Assim como um gravador da operação (...) será possível reproduzir
 * visualmente a história completa de um processo."
 *
 * Reaproveita a Timeline Viva™ já gerada por toda ação (nenhum dado novo é
 * armazenado — decisão §10.20); esta camada só calcula os tempos decorridos
 * entre passos para a reprodução.
 */
final class ReplayService
{
    public function __construct(
        private readonly TimelineRepository $timeline = new TimelineRepository(),
    ) {
    }

    /**
     * @return array<int, array{
     *   id: int, title: string, description: ?string, icon: string, color: string,
     *   created_at: string, actor: ?string,
     *   seconds_since_start: int, seconds_since_previous: int
     * }>
     */
    public function stepsFor(int $processId): array
    {
        $rows = $this->timeline->listForReplay($processId);

        if ($rows === []) {
            return [];
        }

        $startTimestamp = strtotime((string) $rows[0]['created_at']);
        $previousTimestamp = $startTimestamp;
        $steps = [];

        foreach ($rows as $row) {
            $timestamp = strtotime((string) $row['created_at']);
            $actor = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));

            $steps[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'color' => $row['color'],
                'created_at' => $row['created_at'],
                'actor' => $actor !== '' ? $actor : null,
                'seconds_since_start' => max(0, $timestamp - $startTimestamp),
                'seconds_since_previous' => max(0, $timestamp - $previousTimestamp),
            ];

            $previousTimestamp = $timestamp;
        }

        return $steps;
    }
}
