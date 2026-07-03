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

    public function notifyUser(int $userId, string $title, string $message, string $severity = 'INFO'): void
    {
        $this->notifications->create($userId, $title, $message, $severity);
    }

    public function notifySupervisors(int $companyId, string $title, string $message, string $severity = 'INFO'): void
    {
        foreach ($this->users->findSupervisorsAndAdmins($companyId) as $userId) {
            $this->notifications->create($userId, $title, $message, $severity);
        }
    }

    /**
     * Evita spam: só notifica de novo se não tiver havido o mesmo alerta
     * nas últimas $withinMinutes.
     */
    public function notifyOnce(int $userId, string $title, string $message, string $severity, int $withinMinutes): void
    {
        if ($this->notifications->existsRecent($userId, $title, $withinMinutes)) {
            return;
        }

        $this->notifications->create($userId, $title, $message, $severity);
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
