<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\MfaService;

/**
 * MFA no login: desafio (introduzir código) e configuração inicial (QR code).
 * Trabalha em estado "meio-autenticado" (mfa_pending_user_id em sessão) — o
 * utilizador só fica realmente autenticado depois de passar o MFA.
 */
final class MfaController extends Controller
{
    /** Página do desafio: pede o código de 6 dígitos. */
    public function challenge(Request $request): never
    {
        $userId = $this->pendingUserId();

        $this->view('Auth/Views/mfa_challenge', [
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function verifyChallenge(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/mfa/challenge');
        }

        $userId = $this->pendingUserId();
        $user = (new UserRepository())->findById($userId);

        if ($user !== null && (new MfaService())->verifyChallenge($user, (string) $request->input('code', ''))) {
            $this->completeLogin($user);
        }

        Session::flash('errors', ['Código inválido. Tente novamente.']);
        Response::redirect('/mfa/challenge');
    }

    /** Configuração inicial: mostra o QR code e o segredo para a app. */
    public function setup(Request $request): never
    {
        $userId = $this->pendingUserId();
        $user = (new UserRepository())->findById($userId);

        if ($user === null) {
            Response::redirect('/login');
        }

        $setup = (new MfaService())->beginSetup((string) $user['username']);

        $this->view('Auth/Views/mfa_setup', [
            'secret' => $setup['secret'],
            'uri' => $setup['uri'],
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function confirmSetup(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/mfa/setup');
        }

        $userId = $this->pendingUserId();

        if ((new MfaService())->confirmSetup($userId, (string) $request->input('code', ''))) {
            $user = (new UserRepository())->findById($userId);
            if ($user !== null) {
                $this->completeLogin($user);
            }
        }

        Session::flash('errors', ['Código inválido. Verifique a app e tente novamente.']);
        Response::redirect('/mfa/setup');
    }

    /** Conclui o login e marca o dispositivo como confiável por 24h. */
    private function completeLogin(array $user): never
    {
        (new AuthService())->finishLogin($user);
        (new MfaService())->rememberDevice((int) $user['id']);
        Response::redirect('/dashboard');
    }

    /** Devolve o id pendente ou redireciona para o login se não houver. */
    private function pendingUserId(): int
    {
        $userId = (int) Session::get('mfa_pending_user_id', 0);
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        return $userId;
    }
}
