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

    /**
     * O que cada perfil vê em "Todos os Processos": usado na ficha do
     * Utilizador para avisar se a Visibilidade escolhida faz algum efeito
     * com o Perfil selecionado.
     *
     * @return array<int, string> role_id => 'all' | 'branch' | 'none'
     */
    public function processViewScopeByRole(): array
    {
        $rows = $this->pdo->query("
            SELECT r.id AS role_id, p.code
            FROM tb_role r
            JOIN tb_role_permission rp ON rp.role_id = r.id
            JOIN tb_permission p ON p.id = rp.permission_id
            WHERE p.code IN ('process.view_all', 'process.view_branch')
        ")->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            // "ver tudo" ganha sempre a "ver a filial".
            if ($row['code'] === 'process.view_all') {
                $map[$roleId] = 'all';
            } elseif (($map[$roleId] ?? null) !== 'all') {
                $map[$roleId] = 'branch';
            }
        }

        foreach ($this->listAll() as $role) {
            $map[(int) $role['id']] ??= 'none';
        }

        return $map;
    }
}
