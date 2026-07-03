<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Leitor minimalista de .env (sem dependências externas).
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        // OPS-PRD-001 §10.2 - Timezone: UTC, sempre, independentemente da
        // configuração do servidor (ver também Database::connection()).
        date_default_timezone_set('UTC');

        if (self::$loaded || !is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
            $_ENV[$key] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
