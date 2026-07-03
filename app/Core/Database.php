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
}
