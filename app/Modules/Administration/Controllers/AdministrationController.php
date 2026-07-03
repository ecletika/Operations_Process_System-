<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Repositories\SettingRepository;
use App\Modules\Process\Repositories\PriorityRepository;
use App\Modules\Process\Repositories\StatusRepository;
use App\Modules\Process\Repositories\SubjectRepository;
use App\Traits\AuditTrait;

/**
 * RF-0044 a RF-0047 - Configurações do sistema, sem alterar código.
 * Protegido pela permissão settings.manage (Administrador).
 */
final class AdministrationController extends Controller
{
    use AuditTrait;

    public function index(Request $request): never
    {
        $this->view('Administration/Views/index', [
            'settings' => (new SettingRepository())->listAll(),
            'statuses' => (new StatusRepository())->listAll(),
            'priorities' => (new PriorityRepository())->listAll(),
            'subjects' => (new SubjectRepository())->listAll(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function updateSetting(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $key = $params['key'];
        $value = (string) $request->input('value', '');
        $userId = (int) Session::get('user_id');

        (new SettingRepository())->updateValue($key, $value, $userId);
        $this->logAudit('UPDATE', 'tb_setting', 0, null, ['key' => $key, 'value' => $value]);

        Session::flash('success', "Configuração '{$key}' atualizada.");
        $this->back();
    }

    public function updateStatus(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $name = trim((string) $request->input('name', ''));
        $sortOrder = (int) $request->input('sort_order', 0);
        $active = $request->input('active') !== null;
        $userId = (int) Session::get('user_id');

        $repository = new StatusRepository();
        $repository->update($id, $name, $sortOrder, $userId);
        $repository->toggleActive($id, $active, $userId);

        $this->logAudit('UPDATE', 'tb_status', $id, null, ['name' => $name, 'sort_order' => $sortOrder, 'active' => $active]);

        Session::flash('success', 'Estado atualizado.');
        $this->back();
    }

    public function createPriority(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $userId = (int) Session::get('user_id');
        $sla = $request->input('default_sla_minutes');

        (new PriorityRepository())->create(
            trim((string) $request->input('code', '')),
            trim((string) $request->input('name', '')),
            (string) $request->input('color', '#6b7280'),
            (int) $request->input('sort_order', 0),
            $sla !== null && $sla !== '' ? (int) $sla : null,
            $userId
        );

        Session::flash('success', 'Prioridade criada.');
        $this->back();
    }

    public function updatePriority(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $userId = (int) Session::get('user_id');
        $sla = $request->input('default_sla_minutes');

        (new PriorityRepository())->update(
            $id,
            trim((string) $request->input('name', '')),
            (string) $request->input('color', '#6b7280'),
            (int) $request->input('sort_order', 0),
            $sla !== null && $sla !== '' ? (int) $sla : null,
            $userId
        );

        $this->logAudit('UPDATE', 'tb_priority', $id);
        Session::flash('success', 'Prioridade atualizada.');
        $this->back();
    }

    public function togglePriority(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $active = $request->input('active') === '1';
        (new PriorityRepository())->toggleActive($id, $active, (int) Session::get('user_id'));

        $this->back();
    }

    public function createSubject(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        (new SubjectRepository())->create(
            trim((string) $request->input('code', '')),
            trim((string) $request->input('name', '')),
            (int) Session::get('user_id')
        );

        Session::flash('success', 'Assunto criado.');
        $this->back();
    }

    public function updateSubject(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        (new SubjectRepository())->update($id, trim((string) $request->input('name', '')), (int) Session::get('user_id'));

        $this->logAudit('UPDATE', 'tb_subject', $id);
        Session::flash('success', 'Assunto atualizado.');
        $this->back();
    }

    public function toggleSubject(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            $this->back();
        }

        $id = (int) $params['id'];
        $active = $request->input('active') === '1';
        (new SubjectRepository())->toggleActive($id, $active, (int) Session::get('user_id'));

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
        Response::redirect('/admin');
    }
}
