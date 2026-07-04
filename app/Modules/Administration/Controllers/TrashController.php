<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\TrashRepository;
use App\Traits\AuditTrait;

/**
 * 🗑️ Lixeira / Reciclagem — restaurar registos excluídos, individualmente
 * ou todos de uma vez. Permissão records.delete.
 */
final class TrashController extends Controller
{
    use AuditTrait;

    public function index(Request $request): never
    {
        $this->view('Administration/Views/trash', [
            'groups' => (new TrashRepository())->listDeleted(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function restore(Request $request, array $params): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            $this->back();
        }

        $entity = (string) $params['entity'];
        $id = (int) $params['id'];
        $repository = new TrashRepository();

        if ($repository->restore($entity, $id, (int) Session::get('user_id'))) {
            $this->logAudit('RESTORE', 'tb_' . $entity, $id);
            Session::flash('success', 'Registo restaurado com sucesso.');
        } else {
            Session::flash('errors', ['Não foi possível restaurar o registo.']);
        }

        $this->back();
    }

    public function restoreAll(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            $this->back();
        }

        $total = (new TrashRepository())->restoreAll((int) Session::get('user_id'));
        $this->logAudit('RESTORE_ALL', 'trash', 0, null, ['restored' => $total]);
        Session::flash('success', "{$total} registo(s) restaurado(s).");

        $this->back();
    }

    private function back(): never
    {
        Response::redirect('/admin/trash');
    }
}
