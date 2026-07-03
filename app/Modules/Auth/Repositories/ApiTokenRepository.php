<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-SQL-001 007_api_tokens.sql - tokens de acesso pessoal (Fase 5 API REST).
 */
final class ApiTokenRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $userId, string $name, string $tokenHash, ?string $expiresAt): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_api_token (uuid, user_id, name, token_hash, expires_at, created_at, created_by)
            VALUES (UUID(), :user_id, :name, :token_hash, :expires_at, NOW(), :created_by)
        ');
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findValidByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.*, u.company_id, u.branch_id, u.active AS user_active
            FROM tb_api_token t
            JOIN tb_user u ON u.id = t.user_id
            WHERE t.token_hash = :token_hash
              AND t.deleted_at IS NULL
              AND t.revoked_at IS NULL
              AND (t.expires_at IS NULL OR t.expires_at > NOW())
              AND u.deleted_at IS NULL
        ');
        $stmt->execute(['token_hash' => $tokenHash]);
        $token = $stmt->fetch();

        return $token ?: null;
    }

    public function touch(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE tb_api_token SET last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function revokeByHash(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_api_token SET revoked_at = NOW(), updated_at = NOW() WHERE token_hash = :token_hash
        ');
        $stmt->execute(['token_hash' => $tokenHash]);
    }
}
