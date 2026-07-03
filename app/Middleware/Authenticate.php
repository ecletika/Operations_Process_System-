<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * OPS-PRD-001 3.8 - Processo de Login: sem sessão válida, sem acesso.
 */
final class Authenticate
{
    public function handle(Request $request): void
    {
        if (!Session::has('user_id')) {
            Response::redirect('/login');
        }
    }
}
