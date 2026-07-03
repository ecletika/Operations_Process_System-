<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function view(string $view, array $data = []): never
    {
        extract($data, EXTR_SKIP);
        $viewFile = $view;
        require $viewFile;
        exit;
    }
}
