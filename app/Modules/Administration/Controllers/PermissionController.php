<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\PermissionRepository;
use App\Modules\Administration\Repositories\RoleRepository;
use App\Traits\AuditTrait;

/**
 * 🔑 Perfis & Permissões (ACL) - matriz Perfil × Permissão (OPS-PRD-001 §3.5).
 * O perfil Administrador mantém sempre todas as permissões (não editável),
 * para evitar que alguém se tranque a si próprio fora do sistema.
 */
final class PermissionController extends Controller
{
    use AuditTrait;

    public function index(Request $request): never
    {
        $roles = (new RoleRepository())->listAll();
        $permissions = (new PermissionRepository())->listAll();
        $permissionRepository = new PermissionRepository();

        $matrix = [];
        foreach ($roles as $role) {
            $matrix[(int) $role['id']] = $permissionRepository->permissionIdsForRole((int) $role['id']);
        }

        $this->view('Administration/Views/permissions', [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $matrix,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function save(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/admin/permissions');
        }

        $userId = (int) Session::get('user_id');
        $submitted = (array) $request->input('perms', []);
        $roleRepository = new RoleRepository();
        $permissionRepository = new PermissionRepository();

        foreach ($roleRepository->listAll() as $role) {
            // O Administrador nunca perde permissões - ignorado de propósito.
            if ($role['code'] === 'ROLE_ADMIN') {
                continue;
            }

            $roleId = (int) $role['id'];
            $desired = array_map('intval', (array) ($submitted[$roleId] ?? []));
            $permissionRepository->syncForRole($roleId, $desired, $userId);
        }

        $this->logAudit('UPDATE', 'tb_role_permission', 0, null, ['acl_matrix_saved' => true]);
        Session::flash('success', 'Matriz de permissões atualizada.');
        Response::redirect('/admin/permissions');
    }
}
