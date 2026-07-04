<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Env;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Totp;
use App\Modules\Auth\Repositories\TrustedDeviceRepository;
use App\Modules\Auth\Repositories\UserRepository;

/**
 * Orquestra o MFA (TOTP): configuração inicial, verificação no login e
 * "dispositivos de confiança" (pedir só uma vez por dia).
 */
final class MfaService
{
    private const TRUST_COOKIE = 'ops_mfa_trust';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TrustedDeviceRepository $devices = new TrustedDeviceRepository(),
    ) {
    }

    /**
     * Inicia a configuração: gera um segredo (guardado na sessão até ser
     * confirmado) e devolve o segredo + URI otpauth para o QR code.
     *
     * @return array{secret:string, uri:string}
     */
    public function beginSetup(string $username): array
    {
        $secret = Totp::generateSecret();
        Session::put('mfa_setup_secret', $secret);

        $issuer = (string) Env::get('APP_NAME', 'OPS');

        return ['secret' => $secret, 'uri' => Totp::otpauthUri($secret, $username, $issuer)];
    }

    /** Confirma a configuração: valida o código contra o segredo em sessão. */
    public function confirmSetup(int $userId, string $code): bool
    {
        $secret = (string) Session::get('mfa_setup_secret', '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            return false;
        }

        $this->users->setMfa($userId, $secret, true);
        Session::forget('mfa_setup_secret');

        return true;
    }

    public function disable(int $userId): void
    {
        $this->users->setMfa($userId, null, false);
        $this->devices->forgetAll($userId);
    }

    /** Verifica um código no desafio de login. */
    public function verifyChallenge(array $user, string $code): bool
    {
        $secret = (string) ($user['mfa_secret'] ?? '');

        return $secret !== '' && Totp::verify($secret, $code);
    }

    /** Marca este dispositivo como confiável durante as próximas N horas. */
    public function rememberDevice(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hours = (int) Settings::get('mfa_trust_hours', 24);

        $this->devices->remember(
            $userId,
            hash('sha256', $token),
            $hours,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        setcookie(self::TRUST_COOKIE, $token, [
            'expires' => time() + $hours * 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== ''),
        ]);
    }

    /** Verdadeiro se este dispositivo já passou o MFA e ainda está dentro da janela. */
    public function isDeviceTrusted(int $userId): bool
    {
        $token = $_COOKIE[self::TRUST_COOKIE] ?? '';
        if ($token === '') {
            return false;
        }

        return $this->devices->isTrusted($userId, hash('sha256', $token));
    }
}
