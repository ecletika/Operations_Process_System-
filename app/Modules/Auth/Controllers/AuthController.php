<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Validators\LoginValidator;
use RuntimeException;

final class AuthController extends Controller
{
    public function showLogin(Request $request): never
    {
        if (Session::has('user_id')) {
            Response::redirect('/dashboard');
        }

        $this->view('Auth/Views/login', [
            'errors' => Session::pullFlash('errors', []),
            'old' => Session::pullFlash('old', []),
        ]);
    }

    public function login(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/login');
        }

        $input = [
            'username' => trim((string) $request->input('username', '')),
            'password' => (string) $request->input('password', ''),
        ];

        $errors = (new LoginValidator())->validate($input);

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', ['username' => $input['username']]);
            Response::redirect('/login');
        }

        try {
            (new AuthService())->attempt($input['username'], $input['password'], $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Session::flash('old', ['username' => $input['username']]);
            Response::redirect('/login');
        }

        Response::redirect('/dashboard');
    }

    public function logout(Request $request): never
    {
        (new AuthService())->logout();
        Response::redirect('/login');
    }
}
