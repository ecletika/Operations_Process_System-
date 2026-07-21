<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Notification\Repositories\NotificationRepository;

/**
 * RF-0038 a RF-0040 - Centro de notificações.
 */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function notifyUser(int $userId, string $title, string $message, string $severity = 'INFO', ?string $link = null): void
    {
        $this->notifications->create($userId, $title, $message, $severity, $link);
    }

    public function notifySupervisors(int $companyId, string $title, string $message, string $severity = 'INFO'): void
    {
        foreach ($this->users->findSupervisorsAndAdmins($companyId) as $userId) {
            $this->notifications->create($userId, $title, $message, $severity);
        }
    }

    /**
     * #6 - Notifica todos os elementos ativos do lote/departamento de destino
     * (ex.: nova lead na fila), exceto quem gerou a ação.
     */
    public function notifyBatchUsers(int $batchId, string $title, string $message, string $severity = 'INFO', ?int $excludeUserId = null): void
    {
        foreach ($this->users->activeUserIdsForBatch($batchId) as $userId) {
            if ($excludeUserId !== null && $userId === $excludeUserId) {
                continue;
            }
            $this->notifications->create($userId, $title, $message, $severity);
        }
    }

    /**
     * Evita spam: só notifica de novo se não tiver havido o mesmo alerta
     * nas últimas $withinMinutes.
     */
    public function notifyOnce(int $userId, string $title, string $message, string $severity, int $withinMinutes, ?string $link = null): void
    {
        if ($this->notifications->existsRecent($userId, $title, $withinMinutes)) {
            return;
        }

        $this->notifications->create($userId, $title, $message, $severity, $link);
    }

    public function listForUser(int $userId, int $limit = 10): array
    {
        return $this->notifications->listForUser($userId, $limit);
    }

    public function countUnread(int $userId): int
    {
        return $this->notifications->countUnread($userId);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->notifications->markRead($id, $userId);
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
