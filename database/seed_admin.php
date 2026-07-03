<?php

declare(strict_types=1);

/**
 * Cria o utilizador Administrador inicial.
 * Uso: php database/seed_admin.php
 */

require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/Database.php';

use App\Core\Env;
use App\Core\Database;

Env::load(__DIR__ . '/../.env');

$pdo = Database::connection();

$username = 'admin';
$email = 'admin@ops.local';
$password = 'Admin@123';

$exists = $pdo->prepare('SELECT id FROM tb_user WHERE username = :username');
$exists->execute(['username' => $username]);
if ($exists->fetch()) {
    fwrite(STDOUT, "Utilizador '{$username}' já existe. Nada a fazer.\n");
    exit(0);
}

$roleId = $pdo->query("SELECT id FROM tb_role WHERE code = 'ROLE_ADMIN'")->fetchColumn();
$companyId = $pdo->query("SELECT id FROM tb_company WHERE code = 'OPS'")->fetchColumn();
$branchId = $pdo->query("SELECT id FROM tb_branch WHERE code = 'LIS'")->fetchColumn();
$departmentId = $pdo->query("SELECT id FROM tb_department WHERE code = 'WORKSHOP'")->fetchColumn();
$batchId = $pdo->query("SELECT id FROM tb_batch WHERE code = 'IL-132'")->fetchColumn();

if (!$roleId || !$companyId || !$branchId || !$departmentId || !$batchId) {
    fwrite(STDERR, "Execute primeiro database/009_seeders.sql antes deste script.\n");
    exit(1);
}

$stmt = $pdo->prepare('
    INSERT INTO tb_user
        (uuid, username, email, password, first_name, last_name, role_id, company_id, branch_id, department_id, active, created_at)
    VALUES
        (UUID(), :username, :email, :password, :first_name, :last_name, :role_id, :company_id, :branch_id, :department_id, 1, NOW())
');

$stmt->execute([
    'username' => $username,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_BCRYPT),
    'first_name' => 'Administrador',
    'last_name' => 'OPS',
    'role_id' => $roleId,
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'department_id' => $departmentId,
]);

$userId = (int) $pdo->lastInsertId();

$pdo->prepare('
    INSERT INTO tb_user_batch (uuid, user_id, batch_id, created_at)
    VALUES (UUID(), :user_id, :batch_id, NOW())
')->execute(['user_id' => $userId, 'batch_id' => $batchId]);

fwrite(STDOUT, "Administrador criado com sucesso.\n");
fwrite(STDOUT, "Username: {$username}\n");
fwrite(STDOUT, "Password: {$password}\n");
fwrite(STDOUT, "Altere a password após o primeiro login.\n");
