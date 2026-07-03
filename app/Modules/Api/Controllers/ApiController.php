<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

/**
 * Base dos controllers da API REST v1 (Fase 5). Envelope de resposta
 * consistente: {"data": ...} em sucesso, {"error": "..."} em falha.
 */
abstract class ApiController
{
    protected function ok(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function fail(string $message, int $status = 400): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
