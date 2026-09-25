<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper fino sobre sessões PHP (OPS-PRD-001 3.12 Sessões).
 */
final class Session
{
    /** Tempo de vida da sessão, em minutos, quando o .env não diz outra coisa. */
    private const LIFETIME_MINUTES_DEFAULT = 480;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $segundos = self::lifetimeMinutes() * 60;

        // As sessões passam a viver numa pasta NOSSA. Em alojamento
        // partilhado (cPanel), o diretório de sessões por omissão é comum a
        // várias contas, e o coletor de lixo de qualquer uma delas apaga
        // ficheiros pelo critério DELA — era isto que punha gente fora da
        // plataforma a meio do trabalho, sem ter estado parada.
        self::useOwnSavePath();

        // Sem isto, quem manda é o gc_maxlifetime do servidor (tipicamente
        // 24 minutos), e não o tempo configurado na aplicação.
        ini_set('session.gc_maxlifetime', (string) $segundos);
        ini_set('session.use_strict_mode', '1');

        session_name((string) Env::get('SESSION_NAME', 'ops_session'));
        session_set_cookie_params([
            // 0 = o cookie morre ao fechar o browser. Quem decide a
            // expiração por inatividade é a aplicação (ver Authenticate).
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => self::isHttps(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Minutos de vida da sessão (SESSION_LIFETIME_MINUTES no .env). */
    public static function lifetimeMinutes(): int
    {
        $minutos = (int) Env::get('SESSION_LIFETIME_MINUTES', self::LIFETIME_MINUTES_DEFAULT);

        return $minutos > 0 ? $minutos : self::LIFETIME_MINUTES_DEFAULT;
    }

    /**
     * Aponta as sessões para storage/sessions. Se a pasta não puder ser
     * criada ou escrita, fica-se pelo comportamento anterior: uma sessão
     * frágil é mau, mas nenhuma sessão deixava a plataforma inutilizável.
     */
    private static function useOwnSavePath(): void
    {
        $caminho = \dirname(__DIR__, 2) . '/storage/sessions';

        if (!is_dir($caminho) && !@mkdir($caminho, 0770, true) && !is_dir($caminho)) {
            return;
        }

        if (is_writable($caminho)) {
            session_save_path($caminho);
        }
    }

    private static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (!self::has('_csrf_token')) {
            self::put('_csrf_token', bin2hex(random_bytes(32)));
        }

        return self::get('_csrf_token');
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }
}
