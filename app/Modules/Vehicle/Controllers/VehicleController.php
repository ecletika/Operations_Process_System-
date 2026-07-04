<?php

declare(strict_types=1);

namespace App\Modules\Vehicle\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Helpers\PlateHelper;
use App\Modules\Process\Repositories\CustomerRepository;
use App\Modules\Process\Repositories\VehicleRepository;
use App\Traits\AuditTrait;

/**
 * Módulo Viaturas (OPS-UI-001 · 🚗): Nova Viatura, Lista de Viaturas,
 * Viaturas Recorrentes, Histórico da Viatura e Pesquisa por Matrícula.
 */
final class VehicleController extends Controller
{
    use AuditTrait;

    public function index(Request $request): never
    {
        $tab = (string) $request->input('tab', 'all');
        if (!in_array($tab, ['all', 'recurrent'], true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->input('q', ''));

        $vehicles = (new VehicleRepository())->listAll(
            $search,
            $tab === 'recurrent',
            (int) Settings::get('recurrence_window_days', 90),
            (int) Settings::get('recurrent_vehicle_threshold', 3)
        );

        $this->view('Vehicle/Views/index', [
            'vehicles' => $vehicles,
            'tab' => $tab,
            'search' => $search,
            'recurrentThreshold' => (int) Settings::get('recurrent_vehicle_threshold', 3),
            'windowDays' => (int) Settings::get('recurrence_window_days', 90),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    /** Histórico da Viatura: dados + todos os processos da matrícula. */
    public function show(Request $request, array $params): never
    {
        $id = (int) $params['id'];
        $repository = new VehicleRepository();
        $vehicle = $repository->findById($id);

        if ($vehicle === null) {
            http_response_code(404);
            echo 'Viatura não encontrada.';
            exit;
        }

        $this->view('Vehicle/Views/show', [
            'vehicle' => $vehicle,
            'customer' => (new CustomerRepository())->findById((int) $vehicle['customer_id']),
            'processes' => $repository->processesFor($id),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function create(Request $request): never
    {
        $this->view('Vehicle/Views/create', [
            'errors' => Session::pullFlash('errors', []),
            'old' => Session::pullFlash('old', []),
        ]);
    }

    public function store(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/vehicles/create');
        }

        $plate = trim((string) $request->input('plate', ''));
        $customerPhone = trim((string) $request->input('customer_phone', ''));
        $brand = trim((string) $request->input('brand', ''));
        $model = trim((string) $request->input('model', ''));

        $errors = [];
        if ($plate === '') {
            $errors[] = 'A matrícula é obrigatória.';
        }
        if ($customerPhone === '') {
            $errors[] = 'O telefone do cliente é obrigatório (a viatura pertence sempre a um cliente).';
        }

        $vehicleRepository = new VehicleRepository();
        $customer = null;

        if ($plate !== '' && $vehicleRepository->findByPlate($plate) !== null) {
            $errors[] = 'Já existe uma viatura com esta matrícula.';
        }

        if ($customerPhone !== '') {
            $customer = (new CustomerRepository())->findByPhone(\App\Helpers\PhoneHelper::normalize($customerPhone));
            if ($customer === null) {
                $errors[] = 'Não existe nenhum cliente com esse telefone. Crie primeiro o cliente em Clientes → Novo Cliente.';
            }
        }

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            Response::redirect('/vehicles/create');
        }

        $vehicleId = $vehicleRepository->create(
            $plate,
            (int) $customer['id'],
            (int) Session::get('user_id'),
            $brand !== '' ? $brand : null,
            $model !== '' ? $model : null
        );
        $this->logAudit('CREATE', 'tb_vehicle', $vehicleId, null, ['plate' => PlateHelper::normalize($plate)]);

        Session::flash('success', 'Viatura criada.');
        Response::redirect('/vehicles/' . $vehicleId);
    }

    public function update(Request $request, array $params): never
    {
        $id = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/vehicles/' . $id);
        }

        $brand = trim((string) $request->input('brand', ''));
        $model = trim((string) $request->input('model', ''));
        $year = (int) $request->input('year', 0);

        (new VehicleRepository())->update(
            $id,
            $brand !== '' ? $brand : null,
            $model !== '' ? $model : null,
            $year > 1900 ? $year : null,
            (int) Session::get('user_id')
        );
        $this->logAudit('UPDATE', 'tb_vehicle', $id);

        Session::flash('success', 'Viatura atualizada.');
        Response::redirect('/vehicles/' . $id);
    }

    /** Soft-delete da viatura — permissão records.delete. Recuperável na Lixeira. */
    public function destroy(Request $request, array $params): never
    {
        $id = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/vehicles/' . $id);
        }

        $repository = new VehicleRepository();
        $vehicle = $repository->findById($id);

        if ($vehicle !== null) {
            $repository->delete($id, (int) Session::get('user_id'));
            $this->logAudit('DELETE', 'tb_vehicle', $id, ['plate' => $vehicle['plate']], null);
            Session::flash('success', 'Viatura movida para a Lixeira.');
        }

        Response::redirect('/vehicles');
    }
}
