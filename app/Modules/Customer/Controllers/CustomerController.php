<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Helpers\PhoneHelper;
use App\Modules\Process\Repositories\CustomerRepository;
use App\Traits\AuditTrait;

/**
 * Módulo Clientes (OPS-UI-001 · 👥): Novo Cliente, Lista de Clientes,
 * Clientes Frequentes, Histórico do Cliente e Contactos.
 */
final class CustomerController extends Controller
{
    use AuditTrait;

    public function index(Request $request): never
    {
        $tab = (string) $request->input('tab', 'all');
        if (!in_array($tab, ['all', 'frequent'], true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->input('q', ''));

        $customers = (new CustomerRepository())->listAll(
            $search,
            $tab === 'frequent',
            (int) Settings::get('recurrence_window_days', 90),
            (int) Settings::get('frequent_customer_threshold', 5)
        );

        $this->view('Customer/Views/index', [
            'customers' => $customers,
            'tab' => $tab,
            'search' => $search,
            'frequentThreshold' => (int) Settings::get('frequent_customer_threshold', 5),
            'windowDays' => (int) Settings::get('recurrence_window_days', 90),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    /** Histórico do Cliente: dados, viaturas, processos e contactos. */
    public function show(Request $request, array $params): never
    {
        $id = (int) $params['id'];
        $repository = new CustomerRepository();
        $customer = $repository->findById($id);

        if ($customer === null) {
            http_response_code(404);
            echo 'Cliente não encontrado.';
            exit;
        }

        $this->view('Customer/Views/show', [
            'customer' => $customer,
            'vehicles' => $repository->vehiclesFor($id),
            'processes' => $repository->processesFor($id),
            'interactions' => $repository->interactionsFor($id),
            'success' => Session::pullFlash('success'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function create(Request $request): never
    {
        $this->view('Customer/Views/create', [
            'errors' => Session::pullFlash('errors', []),
            'old' => Session::pullFlash('old', []),
        ]);
    }

    public function store(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/customers/create');
        }

        $name = trim((string) $request->input('name', ''));
        $phone = PhoneHelper::normalize(trim((string) $request->input('phone', '')));
        $email = trim((string) $request->input('email', ''));

        $errors = [];
        if ($name === '') {
            $errors[] = 'O nome é obrigatório.';
        }
        if ($phone === '') {
            $errors[] = 'O telefone é obrigatório.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }

        $repository = new CustomerRepository();

        if ($phone !== '' && $repository->findByPhone($phone) !== null) {
            $errors[] = 'Já existe um cliente com este telefone.';
        }

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            Response::redirect('/customers/create');
        }

        $customerId = $repository->create($name, $phone, $email !== '' ? $email : null, (int) Session::get('user_id'));
        $this->logAudit('CREATE', 'tb_customer', $customerId, null, ['name' => $name]);

        Session::flash('success', 'Cliente criado.');
        Response::redirect('/customers/' . $customerId);
    }

    public function update(Request $request, array $params): never
    {
        $id = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/customers/' . $id);
        }

        $name = trim((string) $request->input('name', ''));
        $phone = PhoneHelper::normalize(trim((string) $request->input('phone', '')));
        $email = trim((string) $request->input('email', ''));
        $nif = trim((string) $request->input('nif', ''));

        if ($name === '' || $phone === '') {
            Session::flash('errors', ['Nome e telefone são obrigatórios.']);
            Response::redirect('/customers/' . $id);
        }

        (new CustomerRepository())->update($id, $name, $phone, $email !== '' ? $email : null, $nif !== '' ? $nif : null, (int) Session::get('user_id'));
        $this->logAudit('UPDATE', 'tb_customer', $id);

        Session::flash('success', 'Cliente atualizado.');
        Response::redirect('/customers/' . $id);
    }
}
