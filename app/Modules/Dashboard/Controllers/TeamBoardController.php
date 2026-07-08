<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Auth\Repositories\UserRepository;

/**
 * 🖥️ Tela Operacional — presença da equipa em tempo real: todos os
 * colaboradores ativos, separados por Filial · Departamento, com luz
 * verde (com sessão iniciada) ou vermelha (sem sessão). Filtros por
 * filial, departamento e estado.
 */
final class TeamBoardController extends Controller
{
    public function index(Request $request): never
    {
        $filterBranch = trim((string) $request->input('branch', ''));
        $filterDepartment = trim((string) $request->input('department', ''));
        $filterStatus = (string) $request->input('status', '');
        if (!in_array($filterStatus, ['', 'online', 'offline'], true)) {
            $filterStatus = '';
        }

        // Online = atividade real nos últimos 5 minutos (o middleware toca
        // last_activity_at a cada 2 min enquanto a pessoa navega).
        $onlineWindowMinutes = 5;
        $cutoff = time() - $onlineWindowMinutes * 60;

        $users = array_values(array_filter(
            (new UserRepository())->listAll(),
            static fn (array $u): bool => (int) $u['active'] === 1
        ));

        $onlineIds = [];
        foreach ($users as $u) {
            $activity = $u['last_activity_at'] ?? null;
            if ($activity !== null && strtotime((string) $activity) >= $cutoff) {
                $onlineIds[] = (int) $u['id'];
            }
        }

        // Opções dos filtros (a partir dos utilizadores reais).
        $branches = array_values(array_unique(array_column($users, 'branch_name')));
        $departments = array_values(array_unique(array_column($users, 'department_name')));
        sort($branches);
        sort($departments);

        // Aplica os filtros em memória (a equipa é pequena; evita mais SQL).
        $users = array_values(array_filter($users, static function (array $u) use ($filterBranch, $filterDepartment, $filterStatus, $onlineIds): bool {
            if ($filterBranch !== '' && $u['branch_name'] !== $filterBranch) {
                return false;
            }
            if ($filterDepartment !== '' && $u['department_name'] !== $filterDepartment) {
                return false;
            }
            $isOnline = in_array((int) $u['id'], $onlineIds, true);
            if ($filterStatus === 'online' && !$isOnline) {
                return false;
            }
            if ($filterStatus === 'offline' && $isOnline) {
                return false;
            }

            return true;
        }));

        $this->view('Dashboard/Views/team_board', [
            'users' => $users,
            'onlineIds' => $onlineIds,
            'branches' => $branches,
            'departments' => $departments,
            'filterBranch' => $filterBranch,
            'filterDepartment' => $filterDepartment,
            'filterStatus' => $filterStatus,
            'onlineWindowMinutes' => $onlineWindowMinutes,
        ]);
    }
}
