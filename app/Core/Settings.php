<?php

declare(strict_types=1);

namespace App\Core;

/**
 * OPS-PRD-001 TABELA 24 (tb_setting) - parâmetros globais configuráveis
 * pelo Administrador sem alterar código (RF-0044 a RF-0047).
 */
final class Settings
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::$cache = self::loadAll();
        }

        return self::$cache[$key] ?? $default;
    }

    private static function loadAll(): array
    {
        $stmt = Database::connection()->query('SELECT `key`, `value` FROM tb_setting WHERE deleted_at IS NULL');

        return array_column($stmt->fetchAll(), 'value', 'key');
    }
}
