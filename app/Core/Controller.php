<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = []): never
    {
        $viewPath = dirname(__DIR__) . '/Modules/' . $view . '.php';
        Response::view($viewPath, $data);
    }
}
