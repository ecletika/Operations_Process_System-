<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\BranchRepository;
use App\Modules\Administration\Repositories\CompanyRepository;
use App\Modules\Administration\Repositories\DepartmentRepository;
use App\Modules\Administration\Repositories\RoleRepository;
use App\Modules\Administration\Services\UserManagementService;
use App\Modules\Auth\Repositories\UserRepository;
use RuntimeException;

/**
 * RF-0028 a RF-0031 - Gestão de Utilizadores (Administração).
 */
final class UserController extends Controller
{
    public function index(Request $request): never
    {
        $this->render(null, []);
    }

    public function edit(Request $request, array $params): never
    {
        $userId = (int) $params['id'];
        $user = (new UserRepository())->findById($userId);

        if ($user === null) {
            http_response_code(404);
            echo 'Utilizador não encontrado.';
            exit;
        }

        $this->render($user);
    }

    private function render(?array $editingUser): never
    {
        $this->view('Administration/Views/users', [
            'users' => (new UserRepository())->listAll(),
            'roles' => (new RoleRepository())->listActive(),
            'companies' => (new CompanyRepository())->listAll(),
            'allBranches' => (new BranchRepository())->listAll(),
            'allDepartments' => (new DepartmentRepository())->listAll(),
            'errors' => Session::pullFlash('errors', []),
            'old' => Session::pullFlash('old', []),
            'success' => Session::pullFlash('success'),
            'editingUser' => $editingUser,
            // Departamentos já marcados na visibilidade (view_scope = CUSTOM).
            'viewDepartmentIds' => $editingUser !== null
                ? (new UserRepository())->viewDepartmentIdsFor((int) $editingUser['id'])
                : [],
        ]);
    }

    public function store(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $errors = (new UserManagementService())->create($request->all(), (int) Session::get('user_id'));

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            $this->back();
        }

        Session::flash('success', 'Utilizador criado com sucesso.');
        $this->back();
    }

    public function update(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $errors = (new UserManagementService())->update($id, $request->all(), (int) Session::get('user_id'));

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            Response::redirect('/admin/users/' . $id . '/edit');
        }

        Session::flash('success', 'Utilizador atualizado.');
        $this->back();
    }

    public function toggleActive(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $active = $request->input('active') === '1';

        try {
            (new UserManagementService())->setActive($id, $active, (int) Session::get('user_id'));
            Session::flash('success', $active ? 'Utilizador reativado.' : 'Utilizador desativado.');
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        $this->back();
    }

    /** Soft-delete do utilizador — nunca a própria conta. Recuperável na Lixeira. */
    public function destroy(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];

        try {
            (new UserManagementService())->delete($id, (int) Session::get('user_id'));
            Session::flash('success', 'Utilizador movido para a Lixeira.');
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        $this->back();
    }

    /** Repõe o MFA de um utilizador (perdeu o telemóvel) — volta a pedir configuração. */
    public function resetMfa(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        (new \App\Modules\Auth\Services\MfaService())->disable((int) $params['id']);
        Session::flash('success', 'MFA reposto. O utilizador vai configurar de novo no próximo login (se for obrigatório).');
        $this->back();
    }

    /** Reposição de password pelo Administrador (utilizador trancado fora / esqueceu-se). */
    public function resetPassword(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $errors = (new UserManagementService())->resetPassword($id, (string) $request->input('password', ''), (int) Session::get('user_id'));

        if ($errors !== []) {
            Session::flash('errors', $errors);
        } else {
            Session::flash('success', 'Password reposta. Já pode entregar a nova password ao utilizador.');
        }

        $this->back();
    }

    private function checkCsrf(Request $request): bool
    {
        if (Session::verifyCsrfToken($request->input('_csrf'))) {
            return true;
        }

        Session::flash('errors', ['Sessão expirada, tente novamente.']);

        return false;
    }

    private function back(): never
    {
        Response::redirect('/admin/users');
    }
}
