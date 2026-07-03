<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Settings;
use App\Modules\Auth\Repositories\LoginLogRepository;

/**
 * 🔒 Segurança · Acessos & Sessões - Sessões Ativas e Tentativas de Login
 * (Histórico de Acessos). Só leitura, permissão logs.view (Administrador).
 */
final class SecurityController extends Controller
{
    public function index(Request $request): never
    {
        $filter = (string) $request->input('filter', 'all');
        $success = match ($filter) {
            'success' => true,
            'failed' => false,
            default => null,
        };

        $timeout = (int) Settings::get('session_timeout_minutes', 60);
        $repository = new LoginLogRepository();

        $this->view('Administration/Views/security', [
            'filter' => in_array($filter, ['all', 'success', 'failed'], true) ? $filter : 'all',
            'sessionTimeout' => $timeout,
            'activeSessions' => $repository->activeSessions($timeout),
            'attempts' => $repository->recentAttempts($success, 100),
        ]);
    }
}
