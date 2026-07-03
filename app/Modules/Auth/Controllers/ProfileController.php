<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Repositories\UserRepository;
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

        $this->view('Auth/Views/profile', [
            'user' => $user,
            'errors' => Session::pullFlash('errors', []),
            'passwordErrors' => Session::pullFlash('password_errors', []),
            'success' => Session::pullFlash('success'),
            'old' => Session::pullFlash('old', []),
        ]);
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
