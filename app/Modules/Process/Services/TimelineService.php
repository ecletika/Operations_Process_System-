<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Modules\Process\Repositories\EventRepository;
use App\Modules\Process\Repositories\TimelineRepository;

/**
 * OPS-PRD-001 §1.6 Princípio 4: "Toda ação gera um Evento. Se uma ação não
 * gerar um Evento, considera-se que nunca aconteceu."
 *
 * Ponto único usado por todos os módulos para registar Evento + Timeline
 * Viva™ em conjunto, garantindo RN-0025 (toda alteração gera Timeline).
 */
final class TimelineService
{
    private const PRESETS = [
        'PROCESS_CREATED' => ['icon' => 'plus-circle', 'color' => '#2563eb'],
        'PROCESS_ASSIGNED' => ['icon' => 'user-check', 'color' => '#7c3aed'],
        'PROCESS_STATUS_CHANGED' => ['icon' => 'refresh-cw', 'color' => '#f59e0b'],
        'PROCESS_CLOSED' => ['icon' => 'check-circle', 'color' => '#16a34a'],
        'PROCESS_REOPENED' => ['icon' => 'rotate-ccw', 'color' => '#dc2626'],
        'INTERACTION_CREATED' => ['icon' => 'phone', 'color' => '#0891b2'],
        'NOTE_ADDED' => ['icon' => 'file-text', 'color' => '#8b5cf6'],
        'ATTACHMENT_ADDED' => ['icon' => 'paperclip', 'color' => '#0d9488'],
    ];

    public function __construct(
        private readonly EventRepository $events = new EventRepository(),
        private readonly TimelineRepository $timeline = new TimelineRepository(),
    ) {
    }

    public function record(int $processId, string $eventType, string $title, ?string $description, ?int $userId): void
    {
        $eventId = $this->events->create($processId, $eventType, $title, $description, $userId);

        $preset = self::PRESETS[$eventType] ?? ['icon' => 'circle', 'color' => '#6b7280'];

        $this->timeline->create($processId, $eventId, $title, $description, $preset['icon'], $preset['color']);
    }
}
