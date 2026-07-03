<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ApiContext;
use App\Core\Request;

/**
 * Equivalente ao PermissionMiddleware, mas lê do ApiContext (Bearer token)
 * em vez da sessão web.
 */
final class ApiPermissionMiddleware
{
    public function handle(Request $request, string $permission): void
    {
        if (!ApiContext::hasPermission($permission)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => "Sem permissão: {$permission}"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
