<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Core\Database;
use PDO;

/**
 * Dispositivos de confiança do MFA — depois de passar o MFA, o dispositivo
 * fica confiável durante N horas (pedir só uma vez por dia).
 */
final class TrustedDeviceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function remember(int $userId, string $tokenHash, int $hours, ?string $ip, ?string $userAgent): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_mfa_trusted_device (uuid, user_id, token_hash, expires_at, ip_address, user_agent, created_at)
            VALUES (UUID(), :user_id, :token_hash, DATE_ADD(NOW(), INTERVAL :hours HOUR), :ip, :ua, NOW())
        ');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('token_hash', $tokenHash);
        $stmt->bindValue('hours', $hours, PDO::PARAM_INT);
        $stmt->bindValue('ip', $ip);
        $stmt->bindValue('ua', $userAgent !== null ? mb_substr($userAgent, 0, 255) : null);
        $stmt->execute();
    }

    /** Verdadeiro se existir um dispositivo de confiança válido (não expirado). */
    public function isTrusted(int $userId, string $tokenHash): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM tb_mfa_trusted_device
            WHERE user_id = :user_id AND token_hash = :token_hash AND expires_at > NOW()
        ');
        $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** Esquece todos os dispositivos de um utilizador (ao desativar MFA). */
    public function forgetAll(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tb_mfa_trusted_device WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /** Limpeza dos dispositivos expirados (chamado pelo cron). */
    public function purgeExpired(): int
    {
        $stmt = $this->pdo->query('DELETE FROM tb_mfa_trusted_device WHERE expires_at < NOW()');

        return $stmt->rowCount();
    }
}
