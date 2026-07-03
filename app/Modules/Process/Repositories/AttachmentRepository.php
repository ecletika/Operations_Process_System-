<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * OPS-PRD-001 TABELA 17 (tb_attachment) / RF-0025 / RF-0026.
 */
final class AttachmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByChecksum(int $processId, string $checksum): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM tb_attachment
            WHERE process_id = :process_id AND checksum_sha256 = :checksum AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute(['process_id' => $processId, 'checksum' => $checksum]);
        $attachment = $stmt->fetch();

        return $attachment ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_attachment
                (uuid, process_id, original_name, stored_name, mime_type, extension, file_size,
                 checksum_sha256, uploaded_by, created_at, created_by)
            VALUES
                (UUID(), :process_id, :original_name, :stored_name, :mime_type, :extension, :file_size,
                 :checksum_sha256, :uploaded_by, NOW(), :created_by)
        ');
        $stmt->execute($data + ['created_by' => $data['uploaded_by']]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_attachment WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $attachment = $stmt->fetch();

        return $attachment ?: null;
    }

    public function listByProcess(int $processId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT a.*, u.first_name, u.last_name
            FROM tb_attachment a
            JOIN tb_user u ON u.id = a.uploaded_by
            WHERE a.process_id = :process_id AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
        ');
        $stmt->execute(['process_id' => $processId]);

        return $stmt->fetchAll();
    }
}
