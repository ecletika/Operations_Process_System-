<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\ApiAuthenticate;
use App\Middleware\ApiPermissionMiddleware;
use App\Modules\Api\Controllers\ApiAuthController;
use App\Modules\Api\Controllers\ApiNotificationController;
use App\Modules\Api\Controllers\ApiProcessController;

/**
 * Fase 5 - API REST v1 (OPS-PRD-001 §11.23 "API Ready").
 * Autenticação por Bearer token (ver App\Middleware\ApiAuthenticate),
 * independente da sessão web usada em routes/web.php.
 *
 * @var Router $router
 */

$router->post('/api/v1/auth/login', [ApiAuthController::class, 'login']);
$router->post('/api/v1/auth/logout', [ApiAuthController::class, 'logout'], [ApiAuthenticate::class]);
$router->get('/api/v1/auth/me', [ApiAuthController::class, 'me'], [ApiAuthenticate::class]);

$router->get('/api/v1/processes/queue', [ApiProcessController::class, 'queue'], [ApiAuthenticate::class]);
$router->get('/api/v1/processes/mine', [ApiProcessController::class, 'mine'], [ApiAuthenticate::class]);
$router->post('/api/v1/processes', [ApiProcessController::class, 'store'], [ApiAuthenticate::class, [ApiPermissionMiddleware::class, 'process.create']]);
$router->get('/api/v1/processes/{id}', [ApiProcessController::class, 'show'], [ApiAuthenticate::class]);
$router->post('/api/v1/processes/{id}/assume', [ApiProcessController::class, 'assume'], [ApiAuthenticate::class, [ApiPermissionMiddleware::class, 'process.assume']]);
$router->post('/api/v1/processes/{id}/close', [ApiProcessController::class, 'close'], [ApiAuthenticate::class, [ApiPermissionMiddleware::class, 'process.close']]);
$router->post('/api/v1/processes/{id}/reopen', [ApiProcessController::class, 'reopen'], [ApiAuthenticate::class, [ApiPermissionMiddleware::class, 'process.reopen']]);
$router->post('/api/v1/processes/{id}/notes', [ApiProcessController::class, 'addNote'], [ApiAuthenticate::class]);
$router->post('/api/v1/processes/{id}/attachments', [ApiProcessController::class, 'addAttachment'], [ApiAuthenticate::class]);

$router->get('/api/v1/notifications', [ApiNotificationController::class, 'index'], [ApiAuthenticate::class]);
$router->post('/api/v1/notifications/{id}/read', [ApiNotificationController::class, 'markRead'], [ApiAuthenticate::class]);
