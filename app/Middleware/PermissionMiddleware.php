<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * ACL granular por permissão (OPS-PRD-001 3.2 / 3.13).
 * O sistema utiliza permissões granulares e não verificações fixas por perfil,
 * para permitir criar novos perfis sem alterar código.
 */
final class PermissionMiddleware
{
    public function handle(Request $request, string $permission): void
    {
        if (!Session::has('user_id')) {
            Response::redirect('/login');
        }

        $permissions = Session::get('permissions', []);

        if (!in_array($permission, $permissions, true)) {
            Logger::security(sprintf(
                "Acesso negado - utilizador #%s sem permissão '%s' - %s %s",
                Session::get('user_id'),
                $permission,
                $request->method(),
                $request->path()
            ));
            http_response_code(403);
            echo '403 - Sem permissão para aceder a este recurso.';
            exit;
        }
    }
}
