<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\Authenticate;
use App\Middleware\PermissionMiddleware;
use App\Modules\Administration\Controllers\AdministrationController;
use App\Modules\Administration\Controllers\AuditController;
use App\Modules\Administration\Controllers\ImportController;
use App\Modules\Administration\Controllers\LogController;
use App\Modules\Administration\Controllers\OrganizationController;
use App\Modules\Administration\Controllers\PermissionController;
use App\Modules\Administration\Controllers\SecurityController;
use App\Modules\Administration\Controllers\ToolsController;
use App\Modules\Administration\Controllers\TrashController;
use App\Modules\Administration\Controllers\UserController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\MfaController;
use App\Modules\Auth\Controllers\ProfileController;
use App\Modules\Customer\Controllers\CustomerController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Dashboard\Controllers\TeamBoardController;
use App\Modules\Intelligence\Controllers\IntelligenceController;
use App\Modules\Notification\Controllers\NotificationController;
use App\Modules\Process\Controllers\AttachmentController;
use App\Modules\Process\Controllers\InteractionListController;
use App\Modules\Process\Controllers\NoteController;
use App\Modules\Process\Controllers\ProcessController;
use App\Modules\Process\Controllers\SearchController;
use App\Modules\Process\Controllers\TimelineController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Vehicle\Controllers\VehicleController;

/** @var Router $router */

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// MFA no login (estado meio-autenticado, sem Authenticate — verificam a sessão pendente)
$router->get('/mfa/challenge', [MfaController::class, 'challenge']);
$router->post('/mfa/challenge', [MfaController::class, 'verifyChallenge']);
$router->get('/mfa/setup', [MfaController::class, 'setup']);
$router->post('/mfa/setup', [MfaController::class, 'confirmSetup']);

$router->get('/dashboard', [DashboardController::class, 'index'], [Authenticate::class]);
$router->get('/team', [TeamBoardController::class, 'index'], [Authenticate::class]);
$router->get('/search', [SearchController::class, 'index'], [Authenticate::class]);

// 👤 Meu Perfil (autogestão do próprio utilizador)
$router->get('/profile', [ProfileController::class, 'index'], [Authenticate::class]);
$router->post('/profile', [ProfileController::class, 'update'], [Authenticate::class]);
$router->post('/profile/password', [ProfileController::class, 'changePassword'], [Authenticate::class]);
$router->post('/profile/mfa/enable', [ProfileController::class, 'enableMfa'], [Authenticate::class]);
$router->post('/profile/mfa/disable', [ProfileController::class, 'disableMfa'], [Authenticate::class]);

$router->get('/processes/queue', [ProcessController::class, 'queue'], [Authenticate::class]);
$router->post('/processes/next', [ProcessController::class, 'next'], [Authenticate::class, [PermissionMiddleware::class, 'process.assume']]);
$router->get('/processes/mine', [ProcessController::class, 'mine'], [Authenticate::class]);
$router->get('/processes/all', [ProcessController::class, 'all'], [Authenticate::class, [PermissionMiddleware::class, 'process.view_all']]);
$router->get('/processes/all.xls', [ProcessController::class, 'allExcel'], [Authenticate::class, [PermissionMiddleware::class, 'process.view_all']]);
$router->get('/processes/create', [ProcessController::class, 'create'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->get('/processes/vehicle-lookup', [ProcessController::class, 'vehicleLookup'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->post('/processes', [ProcessController::class, 'store'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->get('/processes/{id}', [ProcessController::class, 'show'], [Authenticate::class]);
$router->get('/processes/{id}/replay', [ProcessController::class, 'replay'], [Authenticate::class]);
$router->post('/processes/{id}/assume', [ProcessController::class, 'assume'], [Authenticate::class, [PermissionMiddleware::class, 'process.assume']]);
$router->post('/processes/{id}/close', [ProcessController::class, 'close'], [Authenticate::class, [PermissionMiddleware::class, 'process.close']]);
$router->post('/processes/{id}/reopen', [ProcessController::class, 'reopen'], [Authenticate::class, [PermissionMiddleware::class, 'process.reopen']]);
$router->post('/processes/{id}/delete', [ProcessController::class, 'destroy'], [Authenticate::class, [PermissionMiddleware::class, 'process.delete']]);
$router->post('/processes/{id}/archive', [ProcessController::class, 'archive'], [Authenticate::class, [PermissionMiddleware::class, 'process.close']]);
$router->post('/processes/{id}/release', [ProcessController::class, 'release'], [Authenticate::class, [PermissionMiddleware::class, 'process.assume']]);
$router->post('/processes/{id}/reassign', [ProcessController::class, 'reassign'], [Authenticate::class, [PermissionMiddleware::class, 'process.view_all']]);

$router->post('/processes/{id}/notes', [NoteController::class, 'store'], [Authenticate::class]);
$router->post('/notes/{id}', [NoteController::class, 'update'], [Authenticate::class]);

$router->post('/processes/{id}/attachments', [AttachmentController::class, 'store'], [Authenticate::class]);
$router->get('/attachments/{id}', [AttachmentController::class, 'download'], [Authenticate::class]);

// Módulo Clientes (👥)
$router->get('/customers', [CustomerController::class, 'index'], [Authenticate::class]);
$router->get('/customers/create', [CustomerController::class, 'create'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->post('/customers', [CustomerController::class, 'store'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->get('/customers/{id}', [CustomerController::class, 'show'], [Authenticate::class]);
$router->post('/customers/{id}', [CustomerController::class, 'update'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->post('/customers/{id}/delete', [CustomerController::class, 'destroy'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);

// Módulo Viaturas (🚗)
$router->get('/vehicles', [VehicleController::class, 'index'], [Authenticate::class]);
$router->get('/vehicles/create', [VehicleController::class, 'create'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->post('/vehicles', [VehicleController::class, 'store'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->get('/vehicles/{id}', [VehicleController::class, 'show'], [Authenticate::class]);
$router->post('/vehicles/{id}', [VehicleController::class, 'update'], [Authenticate::class, [PermissionMiddleware::class, 'process.create']]);
$router->post('/vehicles/{id}/delete', [VehicleController::class, 'destroy'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);

// Módulo Interações (💬) e Timeline Global™ (📝)
$router->get('/interactions', [InteractionListController::class, 'index'], [Authenticate::class]);
$router->get('/timeline', [TimelineController::class, 'index'], [Authenticate::class]);

$router->get('/intelligence', [IntelligenceController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);

$router->get('/reports', [ReportController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/heatmap', [ReportController::class, 'heatmap'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/heatmap.xls', [ReportController::class, 'exportHeatmapXls'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/view/{code}', [ReportController::class, 'show'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/view/{code}/excel', [ReportController::class, 'exportReportXls'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/processes.csv', [ReportController::class, 'exportProcessesCsv'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/processes.xls', [ReportController::class, 'exportProcessesXls'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);
$router->get('/reports/processes.pdf', [ReportController::class, 'exportProcessesPdf'], [Authenticate::class, [PermissionMiddleware::class, 'reports.export']]);

$router->get('/notifications', [NotificationController::class, 'index'], [Authenticate::class]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], [Authenticate::class]);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [Authenticate::class]);

$router->get('/admin', [AdministrationController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/settings/{key}', [AdministrationController::class, 'updateSetting'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/statuses/{id}', [AdministrationController::class, 'updateStatus'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/priorities', [AdministrationController::class, 'createPriority'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/priorities/{id}', [AdministrationController::class, 'updatePriority'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/priorities/{id}/toggle', [AdministrationController::class, 'togglePriority'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/subjects', [AdministrationController::class, 'createSubject'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/subjects/{id}', [AdministrationController::class, 'updateSubject'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/subjects/{id}/toggle', [AdministrationController::class, 'toggleSubject'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->get('/admin/subject-departments', [AdministrationController::class, 'subjectDepartments'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/subject-departments/{id}', [AdministrationController::class, 'saveSubjectDepartments'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->get('/admin/import', [ImportController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/import/customers', [ImportController::class, 'importCustomers'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);
$router->post('/admin/import/vehicles', [ImportController::class, 'importVehicles'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);

$router->get('/admin/users', [UserController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/users', [UserController::class, 'store'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/users/{id}', [UserController::class, 'update'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/users/{id}/toggle', [UserController::class, 'toggleActive'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/users/{id}/reset-mfa', [UserController::class, 'resetMfa'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/users/{id}/delete', [UserController::class, 'destroy'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);

$router->get('/admin/permissions', [PermissionController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);
$router->post('/admin/permissions', [PermissionController::class, 'save'], [Authenticate::class, [PermissionMiddleware::class, 'users.manage']]);

$router->get('/admin/organization', [OrganizationController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/companies', [OrganizationController::class, 'createCompany'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/companies/{id}/delete', [OrganizationController::class, 'deleteCompany'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/branches', [OrganizationController::class, 'createBranch'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/branches/{id}/delete', [OrganizationController::class, 'deleteBranch'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/departments', [OrganizationController::class, 'createDepartment'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);
$router->post('/admin/organization/departments/{id}/delete', [OrganizationController::class, 'deleteDepartment'], [Authenticate::class, [PermissionMiddleware::class, 'companies.manage']]);

$router->get('/admin/audit', [AuditController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'audit.view']]);
$router->get('/admin/logs', [LogController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'logs.view']]);
$router->get('/admin/security', [SecurityController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'logs.view']]);
$router->get('/admin/tools', [ToolsController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'settings.manage']]);

$router->get('/admin/trash', [TrashController::class, 'index'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);
$router->post('/admin/trash/restore-all', [TrashController::class, 'restoreAll'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);
$router->post('/admin/trash/{entity}/{id}/restore', [TrashController::class, 'restore'], [Authenticate::class, [PermissionMiddleware::class, 'records.delete']]);

$router->get('/', function (): never {
    \App\Core\Response::redirect('/dashboard');
});
