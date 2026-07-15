<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

final class SubjectRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listActive(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_subject WHERE active = 1 AND deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    /**
     * RF-0047 - Configurar Assuntos (inclui inativos, para gestão).
     */
    public function listAll(): array
    {
        return $this->pdo->query('
            SELECT * FROM tb_subject WHERE deleted_at IS NULL ORDER BY name ASC
        ')->fetchAll();
    }

    /**
     * Assuntos ativos permitidos para um Departamento (#5). Se o departamento
     * não tiver nenhum assunto configurado (ou for null), devolve TODOS os
     * assuntos ativos — retrocompatível: nada muda até alguém configurar.
     */
    public function listActiveForDepartment(?int $departmentId): array
    {
        if ($departmentId === null) {
            return $this->listActive();
        }

        $stmt = $this->pdo->prepare('
            SELECT s.* FROM tb_subject s
            JOIN tb_department_subject ds ON ds.subject_id = s.id
            WHERE ds.department_id = :department_id
              AND s.active = 1 AND s.deleted_at IS NULL
            ORDER BY s.name ASC
        ');
        $stmt->execute(['department_id' => $departmentId]);
        $mapped = $stmt->fetchAll();

        // Sem configuração para este departamento → mostra todos (fallback).
        return $mapped !== [] ? $mapped : $this->listActive();
    }

    /**
     * Mapa department_id => [subject_id, ...] para a tela de configuração.
     *
     * @return array<int, int[]>
     */
    public function subjectIdsByDepartment(): array
    {
        $rows = $this->pdo->query('SELECT department_id, subject_id FROM tb_department_subject')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['department_id']][] = (int) $row['subject_id'];
        }

        return $map;
    }

    /**
     * Substitui os assuntos configurados de um departamento (#5). Uma lista
     * vazia limpa a configuração (volta a mostrar todos os assuntos).
     *
     * @param int[] $subjectIds
     */
    public function setForDepartment(int $departmentId, array $subjectIds, int $userId): void
    {
        $del = $this->pdo->prepare('DELETE FROM tb_department_subject WHERE department_id = :department_id');
        $del->execute(['department_id' => $departmentId]);

        $ins = $this->pdo->prepare('
            INSERT INTO tb_department_subject (department_id, subject_id, created_at, created_by)
            VALUES (:department_id, :subject_id, NOW(), :user_id)
        ');
        foreach (array_unique(array_map('intval', $subjectIds)) as $subjectId) {
            if ($subjectId > 0) {
                $ins->execute(['department_id' => $departmentId, 'subject_id' => $subjectId, 'user_id' => $userId]);
            }
        }
    }

    public function create(string $code, string $name, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_subject (uuid, code, name, active, created_at, created_by)
            VALUES (UUID(), :code, :name, 1, NOW(), :user_id)
        ');
        $stmt->execute(['code' => $code, 'name' => $name, 'user_id' => $userId]);
    }

    public function update(int $id, string $name, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_subject SET name = :name, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'name' => $name, 'user_id' => $userId]);
    }

    public function toggleActive(int $id, bool $active, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_subject SET active = :active, updated_at = NOW(), updated_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0, 'user_id' => $userId]);
    }
}
