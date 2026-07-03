<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Core\ApiContext;
use App\Core\Request;
use App\Modules\Notification\Services\NotificationService;

final class ApiNotificationController extends ApiController
{
    public function index(Request $request): never
    {
        $service = new NotificationService();
        $userId = ApiContext::userId();

        $this->ok([
            'unread_count' => $service->countUnread($userId),
            'notifications' => $service->listForUser($userId, 30),
        ]);
    }

    public function markRead(Request $request, array $params): never
    {
        (new NotificationService())->markRead((int) $params['id'], ApiContext::userId());
        $this->ok(['message' => 'Notificação marcada como lida.']);
    }
}
