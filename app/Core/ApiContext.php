<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Equivalente a Session, mas para pedidos autenticados por Bearer token
 * (Fase 5 - API REST). Um pedido de API não tem cookie de sessão PHP;
 * o ApiAuthenticate middleware preenche isto a partir do token.
 */
final class ApiContext
{
    private static ?array $user = null;

    public static function set(array $user): void
    {
        self::$user = $user;
    }

    public static function userId(): int
    {
        return (int) (self::$user['id'] ?? 0);
    }

    public static function companyId(): int
    {
        return (int) (self::$user['company_id'] ?? 0);
    }

    public static function branchId(): int
    {
        return (int) (self::$user['branch_id'] ?? 0);
    }

    public static function batchId(): ?int
    {
        return self::$user['batch_id'] ?? null;
    }

    public static function roleCode(): string
    {
        return (string) (self::$user['role_code'] ?? '');
    }

    public static function permissions(): array
    {
        return self::$user['permissions'] ?? [];
    }

    public static function hasPermission(string $permission): bool
    {
        return in_array($permission, self::permissions(), true);
    }
}
