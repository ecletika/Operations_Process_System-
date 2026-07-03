<?php

declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Core\ApiContext;
use App\Core\Request;
use App\Modules\Auth\Services\ApiTokenService;
use App\Modules\Auth\Services\AuthService;
use RuntimeException;

/**
 * POST /api/v1/auth/login e /logout - troca credenciais por um Bearer token.
 */
final class ApiAuthController extends ApiController
{
    public function login(Request $request): never
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $deviceName = trim((string) $request->input('device_name', 'api-client'));

        if ($username === '' || $password === '') {
            $this->fail('username e password são obrigatórios.', 422);
        }

        try {
            $user = (new AuthService())->verifyCredentialsForApi($username, $password, $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 401);
        }

        $issued = (new ApiTokenService())->issue((int) $user['id'], $deviceName);

        $this->ok([
            'token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'username' => $user['username'],
            ],
        ], 201);
    }

    public function logout(Request $request): never
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            (new ApiTokenService())->revoke($token);
        }

        $this->ok(['message' => 'Sessão terminada.']);
    }

    public function me(Request $request): never
    {
        $this->ok([
            'id' => ApiContext::userId(),
            'company_id' => ApiContext::companyId(),
            'branch_id' => ApiContext::branchId(),
            'batch_id' => ApiContext::batchId(),
            'role_code' => ApiContext::roleCode(),
            'permissions' => ApiContext::permissions(),
        ]);
    }
}
