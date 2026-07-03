<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 TABELA 16 (tb_note) / RF-0023 / RF-0024.
 */
final class NoteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $processId, string $note, int $authorId, ?int $editedFromRootId = null): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_note (uuid, process_id, note, author_id, version, edited_from, created_at, created_by)
            VALUES (UUID(), :process_id, :note, :author_id, :version, :edited_from, NOW(), :created_by)
        ');
        $stmt->execute([
            'process_id' => $processId,
            'note' => $note,
            'author_id' => $authorId,
            'version' => $editedFromRootId !== null ? $this->nextVersion($editedFromRootId) : 1,
            'edited_from' => $editedFromRootId,
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateText(int $id, string $note): void
    {
        $stmt = $this->pdo->prepare('UPDATE tb_note SET note = :note, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'note' => $note]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_note WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $note = $stmt->fetch();

        return $note ?: null;
    }

    /**
     * RF-0017/RN-0028 estilo - lista cronológica, mais recente primeiro.
     */
    public function listByProcess(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT n.*, u.first_name, u.last_name
            FROM tb_note n
            JOIN tb_user u ON u.id = n.author_id
            WHERE n.process_id = :process_id AND n.deleted_at IS NULL
            ORDER BY n.created_at DESC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }

    private function nextVersion(int $rootNoteId): int
    {
        $stmt = $this->pdo->prepare('SELECT MAX(version) FROM tb_note WHERE id = :id1 OR edited_from = :id2');
        $stmt->execute(['id1' => $rootNoteId, 'id2' => $rootNoteId]);

        return ((int) $stmt->fetchColumn()) + 1;
    }
}
