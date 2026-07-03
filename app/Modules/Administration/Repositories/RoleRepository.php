<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

final class RoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_role WHERE active = 1 AND deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    /** Todos os perfis (para a matriz de permissões / ACL). */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_role WHERE deleted_at IS NULL ORDER BY id ASC
        ')->fetchAll();
    }
}
