<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\MfaService;
use App\Modules\Auth\Services\ProfileService;

/**
 * 👤 Meu Perfil - autogestão dos dados e da password pelo próprio utilizador.
 */
final class ProfileController extends Controller
{
    public function index(Request $request): never
    {
        $user = (new UserRepository())->findById((int) Session::get('user_id'));

        if ($user === null) {
            Response::redirect('/logout');
        }

        // Se o utilizador está a ativar o MFA, geramos aqui o QR/segredo.
        $mfaSetup = null;
        if ($request->input('mfa') === 'setup' && (int) ($user['mfa_enabled'] ?? 0) === 0) {
            $mfaSetup = (new MfaService())->beginSetup((string) $user['username']);
        }

        $this->view('Auth/Views/profile', [
            'user' => $user,
            'mfaSetup' => $mfaSetup,
            'errors' => Session::pullFlash('errors', []),
            'passwordErrors' => Session::pullFlash('password_errors', []),
            'mfaErrors' => Session::pullFlash('mfa_errors', []),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', []),
        ]);
    }

    /** Ativa o MFA para o próprio utilizador (confirma com um código). */
    public function enableMfa(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/profile');
        }

        if ((new MfaService())->confirmSetup((int) Session::get('user_id'), (string) $request->input('code', ''))) {
            Session::flash('success', 'Autenticação de dois fatores ativada.');
            Response::redirect('/profile');
        }

        Session::flash('mfa_errors', ['Código inválido. Verifique a app e tente de novo.']);
        Response::redirect('/profile?mfa=setup');
    }

    /** Desativa o MFA do próprio utilizador. */
    public function disableMfa(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/profile');
        }

        (new MfaService())->disable((int) Session::get('user_id'));
        Session::flash('success', 'Autenticação de dois fatores desativada.');
        Response::redirect('/profile');
    }

    public function update(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/profile');
        }

        $errors = (new ProfileService())->updateProfile((int) Session::get('user_id'), $request->all());

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $request->all());
            Response::redirect('/profile');
        }

        Session::flash('success', 'Perfil atualizado.');
        Response::redirect('/profile');
    }

    public function changePassword(Request $request): never
    {
        if (!$this->checkCsrf($request)) {
            Response::redirect('/profile');
        }

        $errors = (new ProfileService())->changePassword((int) Session::get('user_id'), $request->all());

        if ($errors !== []) {
            Session::flash('password_errors', $errors);
            Response::redirect('/profile');
        }

        Session::flash('success', 'Password alterada com sucesso.');
        Response::redirect('/profile');
    }

    private function checkCsrf(Request $request): bool
    {
        if (Session::verifyCsrfToken($request->input('_csrf'))) {
            return true;
        }

        Session::flash('errors', ['Sessão expirada, tente novamente.']);

        return false;
    }
}
