<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Repositories\BatchRepository;
use App\Modules\Administration\Repositories\DepartmentRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Traits\AuditTrait;
use RuntimeException;

/**
 * RF-0028 a RF-0031 - Gestão de Utilizadores. RF-0030: nunca eliminar, só desativar.
 *
 * O Lote deixou de ser escolhido à mão neste formulário: cada utilizador
 * fica automaticamente associado ao Lote do seu Departamento (1 Lote por
 * Departamento, criado sozinho - ver BatchRepository::ensureForDepartment).
 * Por dentro, a Fila Inteligente™/Dashboard/Relatórios continuam a
 * funcionar exatamente da mesma forma, organizados por lote.
 */
final class UserManagementService
{
    use AuditTrait;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly BatchRepository $batches = new BatchRepository(),
        private readonly DepartmentRepository $departments = new DepartmentRepository(),
    ) {
    }

    /**
     * @return string[] erros de validação (vazio se criado com sucesso)
     */
    public function create(array $input, int $actingUserId): array
    {
        $errors = $this->validateCommon($input);

        $password = (string) ($input['password'] ?? '');
        if (strlen($password) < 8) {
            $errors[] = 'A password deve ter pelo menos 8 caracteres.';
        }

        $username = trim((string) ($input['username'] ?? ''));
        if ($username === '') {
            $errors[] = 'O utilizador (username) é obrigatório.';
        } elseif ($this->users->findByUsername($username) !== null) {
            $errors[] = 'Já existe um utilizador com esse username.';
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && $this->users->findByEmail($email) !== null) {
            $errors[] = 'Já existe um utilizador com esse email.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $departmentId = (int) $input['department_id'];

        $userId = $this->users->create([
            'username' => $username,
            'email' => trim((string) $input['email']),
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'role_id' => (int) $input['role_id'],
            'company_id' => (int) $input['company_id'],
            'branch_id' => (int) $input['branch_id'],
            'department_id' => $departmentId,
            'view_all_batches' => !empty($input['view_all_batches']) ? 1 : 0,
            'created_by' => $actingUserId,
        ]);

        $this->assignDepartmentBatch($userId, $departmentId, $actingUserId);
        $this->saveViewScope($userId, $input, $actingUserId);

        $this->logAudit('CREATE', 'tb_user', $userId, null, ['username' => $username]);

        return [];
    }

    /**
     * @return string[]
     */
    public function update(int $id, array $input, int $actingUserId): array
    {
        $errors = $this->validateCommon($input);

        if ($errors !== []) {
            return $errors;
        }

        $departmentId = (int) $input['department_id'];

        $this->users->update($id, [
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'email' => trim((string) $input['email']),
            'role_id' => (int) $input['role_id'],
            'company_id' => (int) $input['company_id'],
            'branch_id' => (int) $input['branch_id'],
            'department_id' => $departmentId,
            'view_all_batches' => !empty($input['view_all_batches']) ? 1 : 0,
            'updated_by' => $actingUserId,
        ]);

        $this->assignDepartmentBatch($id, $departmentId, $actingUserId);
        $this->saveViewScope($id, $input, $actingUserId);

        $this->logAudit('UPDATE', 'tb_user', $id);

        return [];
    }

    /**
     * Âmbito de visibilidade em "Todos os Processos" (Supervisor de
     * Departamento): só o seu departamento, toda a Filial, ou uma lista de
     * departamentos escolhidos. Não mexe no que ele pode assumir/reatribuir,
     * que continua limitado ao lote do seu departamento.
     */
    private function saveViewScope(int $userId, array $input, int $actingUserId): void
    {
        $scope = (string) ($input['view_scope'] ?? 'OWN');
        if (!in_array($scope, ['OWN', 'BRANCH', 'CUSTOM'], true)) {
            $scope = 'OWN';
        }

        $this->users->setViewScope($userId, $scope, $actingUserId);
        $this->users->syncViewDepartments(
            $userId,
            $scope === 'CUSTOM' ? (array) ($input['view_departments'] ?? []) : [],
            $actingUserId
        );
    }

    /**
     * Garante que o Lote do Departamento existe e associa o utilizador só a
     * esse lote (substitui qualquer atribuição anterior - um utilizador
     * pertence sempre ao lote do seu Departamento atual).
     */
    private function assignDepartmentBatch(int $userId, int $departmentId, int $actingUserId): void
    {
        $department = $this->departments->findById($departmentId);
        $departmentName = $department['name'] ?? ('Departamento #' . $departmentId);

        $batchId = $this->batches->ensureForDepartment($departmentId, $departmentName, $actingUserId);

        $this->users->syncBatches($userId, [$batchId], $actingUserId);
        $this->users->setDefaultBatch($userId, $batchId, $actingUserId);
    }

    public function setActive(int $id, bool $active, int $actingUserId): void
    {
        if ($id === $actingUserId && !$active) {
            throw new RuntimeException('Não pode desativar a sua própria conta.');
        }

        $this->users->setActive($id, $active, $actingUserId);
        $this->logAudit($active ? 'ACTIVATE' : 'DEACTIVATE', 'tb_user', $id);
    }

    /** Soft-delete de um utilizador — nunca a própria conta. Recuperável na Lixeira. */
    public function delete(int $id, int $actingUserId): void
    {
        if ($id === $actingUserId) {
            throw new RuntimeException('Não pode excluir a sua própria conta.');
        }

        $this->users->delete($id, $actingUserId);
        $this->logAudit('DELETE', 'tb_user', $id);
    }

    /**
     * Reposição de password pelo Administrador (ex.: utilizador não consegue
     * entrar porque a password inicial foi mal digitada, ou esqueceu-se).
     *
     * @return string[] erros (vazio se reposta com sucesso)
     */
    public function resetPassword(int $id, string $newPassword, int $actingUserId): array
    {
        if (strlen($newPassword) < 8) {
            return ['A nova password deve ter pelo menos 8 caracteres.'];
        }

        if ($this->users->findById($id) === null) {
            return ['Utilizador não encontrado.'];
        }

        $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT));
        $this->logAudit('UPDATE', 'tb_user', $id, null, ['password_reset' => true, 'by' => $actingUserId]);

        return [];
    }

    /** @return string[] */
    private function validateCommon(array $input): array
    {
        $errors = [];

        foreach (['first_name', 'last_name', 'email', 'role_id', 'company_id', 'branch_id', 'department_id'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = "O campo '{$field}' é obrigatório.";
            }
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }

        return $errors;
    }
}
