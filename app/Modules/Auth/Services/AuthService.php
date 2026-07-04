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

        $this->establishSession($user, $permissions, $batchId);
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

    private function establishSession(array $user, array $permissions, ?int $batchId): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('user_name', $user['first_name'] . ' ' . $user['last_name']);
        Session::put('role_code', $user['role_code']);
        Session::put('role_name', $user['role_name']);
        Session::put('permissions', $permissions);
        Session::put('company_id', (int) $user['company_id']);
        Session::put('branch_id', (int) $user['branch_id']);
        Session::put('batch_id', $batchId);
        Session::put('view_all_batches', !empty($user['view_all_batches']));
    }
}
