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

        // Painel de Filas por Departamento — para quem tem visão de operação
        // (Admin/Supervisor veem tudo; Supervisor de Departamento só o seu âmbito).
        $permissions = (array) Session::get('permissions', []);
        $departmentBoard = null;
        if (in_array('process.view_all', $permissions, true)) {
            $departmentBoard = $service->departmentBoard(null);
        } elseif (in_array('process.view_branch', $permissions, true)) {
            $departmentBoard = $service->departmentBoard($this->viewableDepartmentIds());
        }

        $this->view('Dashboard/Views/index', [
            'userName' => Session::get('user_name'),
            'roleCode' => $roleCode,
            'roleName' => Session::get('role_name'),
            'widgets' => $widgets,
            'departmentBoard' => $departmentBoard,
        ]);
    }

    /**
     * Departamentos que o Supervisor de Departamento vê (mesma regra de
     * "Todos os Processos"): só o seu, toda a Filial, ou os escolhidos.
     *
     * @return array<int>
     */
    private function viewableDepartmentIds(): array
    {
        if ((string) Session::get('view_scope', 'OWN') === 'OWN') {
            $department = Session::get('department_id');

            return $department !== null ? [(int) $department] : [];
        }

        return array_map('intval', (array) Session::get('viewable_department_ids', []));
    }
}
