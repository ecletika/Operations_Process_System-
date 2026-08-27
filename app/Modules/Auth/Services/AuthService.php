<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Settings;
use App\Modules\Auth\Repositories\LoginLogRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Traits\AuditTrait;
use RuntimeException;

/**
 * Aqui fica toda a regra de negócio de autenticação (OPS-PRD-001 11.7).
 * RN-0024 - Toda autenticação deve ser registada na Auditoria.
 */
final class AuthService
{
    use AuditTrait;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly LoginLogRepository $loginLogs = new LoginLogRepository(),
    ) {
    }

    /**
     * @throws RuntimeException quando as credenciais são inválidas ou a conta está bloqueada
     */
    /**
     * Verifica as credenciais e decide o próximo passo do MFA.
     *
     * @return array{status:'authenticated'|'challenge'|'setup', user:array}
     */
    public function attempt(string $username, string $password, string $ip, ?string $userAgent): array
    {
        $user = $this->verifyCredentials($username, $password, $ip, $userAgent);
        $mfa = new MfaService();

        // Utilizador com MFA ativo: pede código, exceto se este dispositivo
        // já passou o MFA nas últimas horas (pedir só uma vez por dia).
        if ((int) ($user['mfa_enabled'] ?? 0) === 1) {
            if ($mfa->isDeviceTrusted((int) $user['id'])) {
                $this->finishLogin($user);

                return ['status' => 'authenticated', 'user' => $user];
            }

            Session::put('mfa_pending_user_id', (int) $user['id']);

            return ['status' => 'challenge', 'user' => $user];
        }

        // MFA obrigatório globalmente mas ainda não configurado: força a configuração.
        if ((int) Settings::get('mfa_required', 0) === 1) {
            Session::put('mfa_pending_user_id', (int) $user['id']);

            return ['status' => 'setup', 'user' => $user];
        }

        $this->finishLogin($user);

        return ['status' => 'authenticated', 'user' => $user];
    }

    /** Completa o login (sessão + auditoria). Chamado após o MFA quando aplicável. */
    public function finishLogin(array $user): void
    {
        $permissions = $this->users->permissionsForRole((int) $user['role_id']);
        $batchId = $this->users->primaryBatchId((int) $user['id']);

        $this->establishSession($user, $permissions, $batchId, $this->workBatchIds($user));
        Session::forget('mfa_pending_user_id');
        $this->logAudit('LOGIN', 'tb_user', (int) $user['id']);
    }

    /**
     * Fase 5 (API REST) - mesma validação de credenciais, mas sem sessão web;
     * quem chama troca o resultado por um token via ApiTokenService.
     *
     * @throws RuntimeException quando as credenciais são inválidas ou a conta está bloqueada
     */
    public function verifyCredentialsForApi(string $username, string $password, string $ip, ?string $userAgent): array
    {
        $user = $this->verifyCredentials($username, $password, $ip, $userAgent);
        $this->logAudit('LOGIN', 'tb_user', (int) $user['id']);

        return $user;
    }

    /**
     * @throws RuntimeException quando as credenciais são inválidas ou a conta está bloqueada
     */
    private function verifyCredentials(string $username, string $password, string $ip, ?string $userAgent): array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            $this->loginLogs->record(null, false, $ip, $userAgent);
            Logger::security("Login falhado - utilizador inexistente '{$username}' - IP {$ip}");
            throw new RuntimeException('Credenciais inválidas.');
        }

        if (!(bool) $user['active']) {
            $this->loginLogs->record((int) $user['id'], false, $ip, $userAgent);
            Logger::security("Login recusado - utilizador '{$username}' inativo - IP {$ip}");
            throw new RuntimeException('Utilizador inativo.');
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            $this->loginLogs->record((int) $user['id'], false, $ip, $userAgent);
            Logger::security("Login recusado - utilizador '{$username}' bloqueado até {$user['locked_until']} - IP {$ip}");
            throw new RuntimeException('Conta bloqueada temporariamente. Tente novamente mais tarde.');
        }

        if (!password_verify($password, $user['password'])) {
            $maxAttempts = (int) Env::get('LOGIN_MAX_ATTEMPTS', 5);
            $lockMinutes = (int) Env::get('LOGIN_LOCK_MINUTES', 15);
            $this->users->registerFailedAttempt((int) $user['id'], $maxAttempts, $lockMinutes);
            $this->loginLogs->record((int) $user['id'], false, $ip, $userAgent);
            Logger::security("Password incorreta para '{$username}' - IP {$ip}");
            throw new RuntimeException('Credenciais inválidas.');
        }

        $this->users->resetFailedAttempts((int) $user['id']);
        $this->loginLogs->record((int) $user['id'], true, $ip, $userAgent);

        return $user;
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        if ($userId !== null) {
            $this->loginLogs->markLogout((int) $userId);
            $this->logAudit('LOGOUT', 'tb_user', (int) $userId);
        }

        Session::destroy();
    }

    /**
     * Lotes em que o utilizador trabalha na Fila Inteligente™: o lote do
     * seu Departamento mais os lotes dos departamentos que a ficha lhe
     * autoriza (Visibilidade = toda a Filial ou departamentos escolhidos).
     *
     * Antes, os departamentos escolhidos só alargavam "Todos os Processos" e
     * a Fila ficava sempre presa ao próprio departamento — um utilizador de
     * CRM autorizado a Colisão nunca via (nem podia assumir) esses processos.
     *
     * @param  array<string, mixed> $user
     * @return int[]
     */
    private function workBatchIds(array $user): array
    {
        $batchIds = $this->users->batchIdsForUser((int) $user['id']);

        $extraDepartmentIds = $this->users->viewableDepartmentIds(
            (int) $user['id'],
            (string) ($user['view_scope'] ?? 'OWN'),
            isset($user['branch_id']) ? (int) $user['branch_id'] : null
        );

        if ($extraDepartmentIds !== []) {
            $batchIds = array_merge($batchIds, $this->users->batchIdsForDepartments($extraDepartmentIds));
        }

        return array_values(array_unique($batchIds));
    }

    private function establishSession(array $user, array $permissions, ?int $batchId, array $allowedBatchIds = []): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('user_name', $user['first_name'] . ' ' . $user['last_name']);
        Session::put('role_code', $user['role_code']);
        Session::put('role_name', $user['role_name']);
        Session::put('permissions', $permissions);
        Session::put('company_id', (int) $user['company_id']);
        Session::put('branch_id', (int) $user['branch_id']);
        Session::put('department_id', (int) $user['department_id']);
        // Visibilidade por departamento: a Filial inteira (BRANCH) ou apenas
        // os departamentos escolhidos (CUSTOM). Alimenta "Todos os Processos"
        // e também a Fila Inteligente™ (ver workBatchIds).
        $viewScope = (string) ($user['view_scope'] ?? 'OWN');
        Session::put('view_scope', $viewScope);
        Session::put('viewable_department_ids', $this->users->viewableDepartmentIds(
            (int) $user['id'],
            $viewScope,
            isset($user['branch_id']) ? (int) $user['branch_id'] : null
        ));
        Session::put('batch_id', $batchId);
        // Lotes que o operador pode ver/assumir: o do seu departamento mais
        // os dos departamentos autorizados na ficha. O isolamento por
        // departamento (RN-0011) usa esta lista: só os processos destes
        // lotes entram na Fila Inteligente™ do operador.
        Session::put('allowed_batch_ids', $allowedBatchIds);
        Session::put('view_all_batches', !empty($user['view_all_batches']));
    }
}
