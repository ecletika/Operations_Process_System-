<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\ApiTokenRepository;
use App\Modules\Auth\Repositories\UserRepository;
use RuntimeException;

/**
 * Fase 5 - API REST. Tokens tipo "Bearer", independentes da sessão web,
 * para permitir integrações e futuras apps móveis (OPS-PRD-001 §11.23 API Ready).
 */
final class ApiTokenService
{
    private const DEFAULT_TTL_DAYS = 90;

    public function __construct(
        private readonly ApiTokenRepository $tokens = new ApiTokenRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * @return array{token: string, expires_at: ?string}
     */
    public function issue(int $userId, string $deviceName): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::DEFAULT_TTL_DAYS . ' days'));

        $this->tokens->create($userId, $deviceName, $tokenHash, $expiresAt);

        return ['token' => $rawToken, 'expires_at' => $expiresAt];
    }

    /**
     * Valida o token e devolve o contexto do utilizador (mesmo formato usado
     * pela sessão web: role_code, permissions, batch_id, company_id...).
     *
     * @throws RuntimeException se o token for inválido, expirado ou revogado
     */
    public function authenticate(string $rawToken): array
    {
        $tokenHash = hash('sha256', $rawToken);
        $tokenRow = $this->tokens->findValidByHash($tokenHash);

        if ($tokenRow === null) {
            throw new RuntimeException('Token inválido, expirado ou revogado.');
        }

        if (!(bool) $tokenRow['user_active']) {
            throw new RuntimeException('Utilizador inativo.');
        }

        $this->tokens->touch((int) $tokenRow['id']);

        $user = $this->users->findById((int) $tokenRow['user_id']);
        if ($user === null) {
            throw new RuntimeException('Utilizador não encontrado.');
        }

        return [
            'id' => (int) $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'company_id' => (int) $user['company_id'],
            'branch_id' => (int) $user['branch_id'],
            'batch_id' => $this->users->primaryBatchId((int) $user['id']),
            'role_code' => $user['role_code'],
            'permissions' => $this->users->permissionsForRole((int) $user['role_id']),
        ];
    }

    public function revoke(string $rawToken): void
    {
        $this->tokens->revokeByHash(hash('sha256', $rawToken));
    }
}
