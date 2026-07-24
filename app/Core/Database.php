<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Ligação PDO única à base de dados (OPS-PRD-001 capítulo 10.2).
 */
final class Database
{
    private static ?PDO $connection = null;
    /** @var array<string, bool> cache de colunas já verificadas ('tabela.coluna') */
    private static array $columns = [];

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_DATABASE', 'ops')
            );

            try {
                self::$connection = new PDO(
                    $dsn,
                    (string) Env::get('DB_USERNAME', 'root'),
                    (string) Env::get('DB_PASSWORD', ''),
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                // OPS-PRD-001 §10.2 - Timezone: UTC. Sem isto, NOW() do MySQL
                // e o relógio do PHP podem divergir consoante a configuração
                // de cada servidor, desalinhando todos os cálculos de SLA.
                self::$connection->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                throw new PDOException('Falha ao ligar à base de dados: ' . $e->getMessage(), (int) $e->getCode());
            }
        }

        return self::$connection;
    }

    /**
     * A coluna existe na base? Serve para o código sobreviver à janela entre
     * o deploy (automático, pelo git) e a migração (manual, no phpMyAdmin):
     * durante esse intervalo a coluna nova ainda não existe, e uma consulta
     * que a peça deitaria abaixo a página inteira.
     *
     * Só aceita nomes vindos do próprio código — nunca do pedido HTTP.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!isset(self::$columns[$key])) {
            $stmt = self::connection()->prepare('
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
            ');
            $stmt->execute(['t' => $table, 'c' => $column]);
            self::$columns[$key] = (int) $stmt->fetchColumn() > 0;
        }

        return self::$columns[$key];
    }
}
