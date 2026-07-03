<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * RF-0044 - Configurar SLA / limites do sistema (tb_setting).
 * Os "keys" são fixos pelo sistema (criados nos seeders); esta tela só
 * permite alterar o valor, nunca criar chaves arbitrárias.
 */
final class SettingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_setting WHERE deleted_at IS NULL ORDER BY `key` ASC
        ')->fetchAll();
    }

    public function updateValue(string $key, string $value, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_setting SET `value` = :value, updated_at = NOW(), updated_by = :user_id WHERE `key` = :key
        ');
        $stmt->execute(['key' => $key, 'value' => $value, 'user_id' => $userId]);
    }
}
