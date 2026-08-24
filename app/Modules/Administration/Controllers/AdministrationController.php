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
            $this->nextContactAutoMinutes($request),
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
            $this->nextContactAutoMinutes($request),
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

    /**
     * Horário de Atendimento + Feriados (contagem do SLA em horário útil).
     */
    public function slaCalendar(Request $request): never
    {
        $repository = new \App\Modules\Administration\Repositories\SlaCalendarRepository();

        $this->view('Administration/Views/sla_calendar', [
            'enabled' => (string) \App\Core\Settings::get('sla_business_hours_enabled', '0') === '1',
            'hours' => $repository->hours(),
            'holidays' => $repository->holidays(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function saveBusinessHours(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-calendar');
        }

        $userId = (int) Session::get('user_id');
        $settings = new SettingRepository();
        $settings->updateValue('sla_business_hours_enabled', $request->input('enabled') !== null ? '1' : '0', $userId);

        $repository = new \App\Modules\Administration\Repositories\SlaCalendarRepository();
        $days = (array) $request->input('days', []);          // [weekday => 1] os que estão abertos
        $opens = (array) $request->input('open', []);         // [weekday => 'HH:MM']
        $closes = (array) $request->input('close', []);       // [weekday => 'HH:MM']
        $lunchStarts = (array) $request->input('lunch_start', []);
        $lunchEnds = (array) $request->input('lunch_end', []);

        for ($wd = 0; $wd <= 6; $wd++) {
            $aberto = isset($days[$wd]);
            $open = $aberto ? trim((string) ($opens[$wd] ?? '')) : null;
            $close = $aberto ? trim((string) ($closes[$wd] ?? '')) : null;
            $lunchStart = $aberto ? trim((string) ($lunchStarts[$wd] ?? '')) : '';
            $lunchEnd = $aberto ? trim((string) ($lunchEnds[$wd] ?? '')) : '';

            // Dia aberto tem de ter horas válidas com fecho depois da abertura.
            if ($aberto && ($open === '' || $close === '' || $close <= $open)) {
                Session::flash('errors', ['Verifique as horas: o fecho tem de ser depois da abertura nos dias abertos.']);
                Response::redirect('/admin/sla-calendar');
            }

            // Almoço: ou os dois vazios (sem almoço), ou os dois preenchidos,
            // com fim depois do início e dentro do horário do dia.
            $temAlmoco = $lunchStart !== '' || $lunchEnd !== '';
            if ($aberto && $temAlmoco) {
                if ($lunchStart === '' || $lunchEnd === '' || $lunchEnd <= $lunchStart
                    || $lunchStart < (string) $open || $lunchEnd > (string) $close) {
                    Session::flash('errors', ['Verifique o almoço: preencha início e fim, com o fim depois do início e ambos dentro do horário de atendimento.']);
                    Response::redirect('/admin/sla-calendar');
                }
            }

            $repository->setDay(
                $wd,
                $open,
                $close,
                $temAlmoco ? $lunchStart : null,
                $temAlmoco ? $lunchEnd : null,
                $userId
            );
        }

        $this->logAudit('UPDATE', 'tb_business_hours', 0, null, ['saved' => true]);
        Session::flash('success', 'Horário de atendimento atualizado.');
        Response::redirect('/admin/sla-calendar');
    }

    public function storeHoliday(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-calendar');
        }

        $name = trim((string) $request->input('name', ''));
        $date = trim((string) $request->input('holiday_date', ''));
        $recurring = $request->input('recurring') !== null;

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($name === '' || $parsed === false) {
            Session::flash('errors', ['Indique um nome e uma data válida para o feriado.']);
            Response::redirect('/admin/sla-calendar');
        }

        (new \App\Modules\Administration\Repositories\SlaCalendarRepository())
            ->addHoliday($parsed->format('Y-m-d'), $name, 'REGIONAL', $recurring, (int) Session::get('user_id'));
        $this->logAudit('CREATE', 'tb_holiday', 0, null, ['name' => $name, 'date' => $date]);

        Session::flash('success', "Feriado \"{$name}\" adicionado.");
        Response::redirect('/admin/sla-calendar');
    }

    public function deleteHoliday(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-calendar');
        }

        (new \App\Modules\Administration\Repositories\SlaCalendarRepository())
            ->deleteHoliday((int) $params['id'], (int) Session::get('user_id'));
        $this->logAudit('DELETE', 'tb_holiday', (int) $params['id']);

        Session::flash('success', 'Feriado removido.');
        Response::redirect('/admin/sla-calendar');
    }

    /**
     * Motivos de Pausa do SLA — os estados que param o relógio do SLA
     * (Aguarda Cliente, Aguarda Peças, ...). Configuráveis como os Assuntos.
     */
    public function slaReasons(Request $request): never
    {
        $repository = new StatusRepository();
        $reasons = $repository->listWaiting();

        // Motivo em uso = há processos parados nele; nesse caso só se desativa.
        foreach ($reasons as $i => $reason) {
            $reasons[$i]['in_use'] = $repository->isInUse((int) $reason['id']);
        }

        $this->view('Administration/Views/sla_reasons', [
            'reasons' => $reasons,
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function storeSlaReason(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-reasons');
        }

        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            Session::flash('errors', ['O nome do motivo é obrigatório.']);
            Response::redirect('/admin/sla-reasons');
        }

        $repository = new StatusRepository();
        $code = $this->slaReasonCode($name);

        if ($repository->findByCode($code) !== null) {
            Session::flash('errors', ["Já existe um estado com o código {$code}. Use outro nome."]);
            Response::redirect('/admin/sla-reasons');
        }

        $repository->createWaiting($code, $name, (int) Session::get('user_id'));
        $this->logAudit('CREATE', 'tb_status', 0, null, ['code' => $code, 'name' => $name, 'is_waiting' => true]);

        Session::flash('success', "Motivo \"{$name}\" criado. Já aparece no processo em \"Pôr em espera\".");
        Response::redirect('/admin/sla-reasons');
    }

    public function updateSlaReason(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-reasons');
        }

        $id = (int) $params['id'];
        $name = trim((string) $request->input('name', ''));
        $userId = (int) Session::get('user_id');
        $repository = new StatusRepository();

        if ($request->input('active') !== null) {
            $repository->toggleActive($id, $request->input('active') === '1', $userId);
            $this->logAudit('UPDATE', 'tb_status', $id, null, ['active' => $request->input('active')]);
            Session::flash('success', 'Motivo atualizado.');
            Response::redirect('/admin/sla-reasons');
        }

        if ($name === '') {
            Session::flash('errors', ['O nome do motivo é obrigatório.']);
            Response::redirect('/admin/sla-reasons');
        }

        $repository->update($id, $name, (int) $request->input('sort_order', 0), $userId);
        $this->logAudit('UPDATE', 'tb_status', $id, null, ['name' => $name]);

        Session::flash('success', 'Motivo atualizado.');
        Response::redirect('/admin/sla-reasons');
    }

    /** Excluir um motivo — só se não houver processos parados nele (preserva o histórico). */
    public function deleteSlaReason(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/sla-reasons');
        }

        $id = (int) $params['id'];
        $repository = new StatusRepository();

        if ($repository->isInUse($id)) {
            Session::flash('errors', ['Há processos neste motivo. Desative-o em vez de o excluir, para não perder o histórico.']);
            Response::redirect('/admin/sla-reasons');
        }

        $repository->deleteWaiting($id, (int) Session::get('user_id'));
        $this->logAudit('DELETE', 'tb_status', $id);

        Session::flash('success', 'Motivo excluído.');
        Response::redirect('/admin/sla-reasons');
    }

    /**
     * Gera o código a partir do nome (ex.: "Aguarda Seguradora" →
     * WAIT_AGUARDA_SEGURADORA). É automático de propósito: assim ninguém
     * cria à mão um código que choque com os estados do fluxo principal
     * (QUEUE, ASSIGNED, SOLVED...), de que a máquina de estados depende.
     */
    private function slaReasonCode(string $name): string
    {
        $ascii = strtr($name, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ]);
        $slug = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $ascii) ?? '', '_'));

        return 'WAIT_' . substr($slug !== '' ? $slug : 'MOTIVO', 0, 25);
    }

    /**
     * #5 - Assuntos por Departamento: matriz para escolher que assuntos
     * aparecem no Novo Processo consoante o departamento escolhido.
     */
    public function subjectDepartments(Request $request): never
    {
        $this->view('Administration/Views/subject_departments', [
            'departments' => (new \App\Modules\Administration\Repositories\DepartmentRepository())->listAll(),
            'subjects' => (new SubjectRepository())->listActive(),
            'map' => (new SubjectRepository())->subjectIdsByDepartment(),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function saveSubjectDepartments(Request $request, array $params): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/admin/subject-departments');
        }

        $departmentId = (int) $params['id'];
        $subjectIds = array_map('intval', (array) $request->input('subjects', []));
        (new SubjectRepository())->setForDepartment($departmentId, $subjectIds, (int) Session::get('user_id'));
        $this->logAudit('UPDATE', 'tb_department_subject', $departmentId, null, ['subjects' => $subjectIds]);

        Session::flash('success', 'Assuntos do departamento atualizados.');
        Response::redirect('/admin/subject-departments');
    }

    /**
     * Minutos entre contactos com o cliente de uma Prioridade: campo vazio ou 0
     * significa "não configurado" — o operador escolhe a data no calendário do
     * processo. Guarda-se NULL nesse caso, para não haver dois valores a dizer
     * o mesmo.
     */
    private function nextContactAutoMinutes(Request $request): ?int
    {
        $hours = $request->input('next_contact_auto_minutes');

        if ($hours === null || trim((string) $hours) === '' || (int) $hours <= 0) {
            return null;
        }

        return (int) $hours;
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
