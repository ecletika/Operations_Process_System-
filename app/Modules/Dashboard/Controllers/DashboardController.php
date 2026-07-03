<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Modules\Dashboard\Services\DashboardService;

/**
 * Cada perfil entra diretamente no seu painel (OPS-PRD-001 3.8 / 3.9 / capítulo 8).
 */
final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $roleCode = (string) Session::get('role_code');
        $service = new DashboardService();

        $widgets = match ($roleCode) {
            'ROLE_ADMIN' => $service->adminWidgets(),
            'ROLE_SUPERVISOR' => $service->supervisorWidgets(),
            default => $service->operatorWidgets((int) Session::get('user_id'), Session::get('batch_id')),
        };

        $this->view('Dashboard/Views/index', [
            'userName' => Session::get('user_name'),
            'roleCode' => $roleCode,
            'roleName' => Session::get('role_name'),
            'widgets' => $widgets,
        ]);
    }
}
