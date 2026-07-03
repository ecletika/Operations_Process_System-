<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

Env::load(__DIR__ . '/../.env');

if ((bool) Env::get('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

$isApiRequest = str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/api/');

if (!$isApiRequest) {
    // A API REST (Fase 5) é stateless via Bearer token; não precisa de cookie de sessão.
    Session::start();
}

$router = new Router();
require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

$router->dispatch(new Request());
