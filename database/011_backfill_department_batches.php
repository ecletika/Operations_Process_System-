<?php

declare(strict_types=1);

/**
 * Backfill único: garante que todos os Departamentos já existentes têm o
 * seu Lote automático, e reconcilia os utilizadores existentes para
 * ficarem associados ao lote do seu Departamento atual.
 *
 * Uso: php database/011_backfill_department_batches.php
 */

use App\Core\Database;
use App\Core\Env;
use App\Modules\Administration\Repositories\BatchRepository;
use App\Modules\Administration\Repositories\DepartmentRepository;
use App\Modules\Auth\Repositories\UserRepository;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

Env::load(__DIR__ . '/../.env');

$pdo = Database::connection();
$departmentRepository = new DepartmentRepository();
$batchRepository = new BatchRepository();
$userRepository = new UserRepository();

$systemUserId = (int) $pdo->query("SELECT id FROM tb_user WHERE username = 'admin' LIMIT 1")->fetchColumn();
if ($systemUserId <= 0) {
    $systemUserId = 1;
}

echo "A garantir Lote automático para cada Departamento...\n";
$departments = $departmentRepository->listAll();
$batchByDepartment = [];

foreach ($departments as $department) {
    $batchId = $batchRepository->ensureForDepartment((int) $department['id'], $department['name'], $systemUserId);
    $batchByDepartment[(int) $department['id']] = $batchId;
    echo "  - {$department['name']}: lote #{$batchId}\n";
}

echo "\nA reconciliar utilizadores existentes com o lote do seu Departamento...\n";
$users = $pdo->query('SELECT id, username, department_id FROM tb_user WHERE deleted_at IS NULL')->fetchAll();

foreach ($users as $user) {
    $departmentId = (int) $user['department_id'];
    $batchId = $batchByDepartment[$departmentId] ?? null;

    if ($batchId === null) {
        echo "  ! {$user['username']}: departamento #{$departmentId} sem lote, ignorado\n";
        continue;
    }

    $userRepository->syncBatches((int) $user['id'], [$batchId], $systemUserId);
    $userRepository->setDefaultBatch((int) $user['id'], $batchId, $systemUserId);
    echo "  - {$user['username']}: lote #{$batchId}\n";
}

echo "\nConcluído.\n";
