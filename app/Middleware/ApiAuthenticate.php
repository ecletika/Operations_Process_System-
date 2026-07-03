<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ApiContext;
use App\Core\Request;
use App\Modules\Auth\Services\ApiTokenService;
use RuntimeException;

/**
 * Fase 5 - autenticação por Bearer token (equivalente ao Authenticate,
 * mas para a API REST, que não usa cookies de sessão).
 */
final class ApiAuthenticate
{
    public function handle(Request $request): void
    {
        $header = $request->bearerToken();

        if ($header === null) {
            $this->unauthorized('Token em falta. Envie "Authorization: Bearer <token>".');
        }

        try {
            $user = (new ApiTokenService())->authenticate($header);
        } catch (RuntimeException $e) {
            $this->unauthorized($e->getMessage());
            return;
        }

        ApiContext::set($user);
    }

    private function unauthorized(string $message): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
