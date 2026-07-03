<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\BatchRepository;
use App\Modules\Administration\Repositories\BranchRepository;
use App\Modules\Administration\Repositories\CompanyRepository;
use App\Modules\Administration\Repositories\DepartmentRepository;

/**
 * RF-0032 a RF-0035 - Empresas, Filiais, Departamentos e Lotes.
 */
final class OrganizationController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('Administration/Views/organization', [
            'companies' => (new CompanyRepository())->listAll(),
            'branches' => (new BranchRepository())->listAll(),
            'departments' => (new DepartmentRepository())->listAll(),
            'batches' => (new BatchRepository())->listAll(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function createCompany(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        (new CompanyRepository())->create(
            trim((string) $request->input('code', '')),
            trim((string) $request->input('name', '')),
            (int) Session::get('user_id')
        );

        Session::flash('success', 'Empresa criada.');
        $this->back();
    }

    public function deleteCompany(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $repository = new CompanyRepository();
        $id = (int) $params['id'];

        if ($repository->hasActiveBranches($id)) {
            Session::flash('errors', ['Não é possível excluir: esta empresa ainda tem filiais associadas.']);
            $this->back();
        }

        $repository->delete($id, (int) Session::get('user_id'));
        Session::flash('success', 'Empresa excluída.');
        $this->back();
    }

    public function createBranch(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        (new BranchRepository())->create(
            (int) $request->input('company_id', 0),
            trim((string) $request->input('code', '')),
            trim((string) $request->input('name', '')),
            (int) Session::get('user_id')
        );

        Session::flash('success', 'Filial criada.');
        $this->back();
    }

    public function deleteBranch(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $repository = new BranchRepository();
        $id = (int) $params['id'];

        if ($repository->hasActiveDepartments($id)) {
            Session::flash('errors', ['Não é possível excluir: esta filial ainda tem departamentos associados.']);
            $this->back();
        }

        $repository->delete($id, (int) Session::get('user_id'));
        Session::flash('success', 'Filial excluída.');
        $this->back();
    }

    public function createDepartment(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $userId = (int) Session::get('user_id');
        $name = trim((string) $request->input('name', ''));

        $departmentId = (new DepartmentRepository())->create(
            (int) $request->input('branch_id', 0),
            trim((string) $request->input('code', '')),
            $name,
            $userId
        );

        // O Lote deixou de ser gerido à mão: cada Departamento tem sempre um
        // Lote próprio, criado logo aqui. A Fila Inteligente™/Dashboard
        // continuam organizados por lote "por dentro", sem o admin ter de mexer nisso.
        (new BatchRepository())->ensureForDepartment($departmentId, $name, $userId);

        Session::flash('success', 'Departamento criado.');
        $this->back();
    }

    public function deleteDepartment(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $userId = (int) Session::get('user_id');
        $batchRepository = new BatchRepository();

        // O Lote é automático (1 por Departamento) - já não bloqueia a
        // exclusão do Departamento por si só; o que bloqueia é o Lote estar
        // em uso de verdade (processos/utilizadores associados a ele).
        $batch = $batchRepository->findActiveByDepartment($id);
        if ($batch !== null && $batchRepository->isInUse((int) $batch['id'])) {
            Session::flash('errors', ['Não é possível excluir: este departamento ainda tem processos ou utilizadores associados (através do lote automático).']);
            $this->back();
        }

        if ($batch !== null) {
            $batchRepository->delete((int) $batch['id'], $userId);
        }

        (new DepartmentRepository())->delete($id, $userId);
        Session::flash('success', 'Departamento excluído.');
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
        Response::redirect('/admin/organization');
    }
}
