<?php

declare(strict_types=1);

namespace App\Modules\Notification\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Notification\Services\NotificationService;

final class NotificationController extends Controller
{
    public function index(Request $request): never
    {
        $userId = (int) Session::get('user_id');
        $service = new NotificationService();

        $this->view('Notification/Views/index', [
            'notifications' => $service->listForUser($userId, 30),
        ]);
    }

    public function markRead(Request $request, array $params): never
    {
        if (Session::verifyCsrfToken($request->input('_csrf'))) {
            (new NotificationService())->markRead((int) $params['id'], (int) Session::get('user_id'));
        }

        Response::redirect($this->safeRedirect($request));
    }

    public function markAllRead(Request $request): never
    {
        if (Session::verifyCsrfToken($request->input('_csrf'))) {
            (new NotificationService())->markAllRead((int) Session::get('user_id'));
        }

        Response::redirect($this->safeRedirect($request));
    }

    /** Só aceita caminhos internos relativos, para evitar open redirect. */
    private function safeRedirect(Request $request): string
    {
        $to = (string) $request->input('redirect_to', '/notifications');

        return str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/notifications';
    }
}
