<?php

declare(strict_types=1);

namespace App\Modules\Process\Services;

use App\Modules\Process\Repositories\AttachmentRepository;
use RuntimeException;

/**
 * RF-0025 (tipos permitidos) / RF-0026 (pré-visualização) / RN-0035/0036.
 * "Nunca gravar uploads dentro do código." (§11.21) - guardados em storage/uploads,
 * fora do document root público.
 */
final class AttachmentService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'zip'];
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20MB

    public function __construct(
        private readonly AttachmentRepository $attachments = new AttachmentRepository(),
        private readonly TimelineService $timeline = new TimelineService(),
    ) {
    }

    /**
     * @param array $file uma entrada de $_FILES (ex.: $_FILES['file'])
     */
    public function upload(int $processId, array $file, int $userId): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao enviar o ficheiro.');
        }

        if ((int) $file['size'] > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('Ficheiro demasiado grande (máx. 20MB).');
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Tipo de ficheiro não permitido. Aceites: ' . implode(', ', self::ALLOWED_EXTENSIONS) . '.');
        }

        $tmpPath = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload inválido.');
        }

        $checksum = hash_file('sha256', $tmpPath);

        // TABELA 17 - checksum evita duplicados no mesmo processo.
        $existing = $this->attachments->findByChecksum($processId, $checksum);
        if ($existing !== null) {
            throw new RuntimeException('Este ficheiro já foi anexado a este processo.');
        }

        $storedName = $this->generateStoredName($extension);
        $destination = $this->destinationDir($processId) . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Não foi possível guardar o ficheiro.');
        }

        $this->attachments->create([
            'process_id' => $processId,
            'original_name' => $originalName,
            'stored_name' => $this->relativePath($processId, $storedName),
            'mime_type' => (string) $file['type'],
            'extension' => $extension,
            'file_size' => (int) $file['size'],
            'checksum_sha256' => $checksum,
            'uploaded_by' => $userId,
        ]);

        $this->timeline->record($processId, 'ATTACHMENT_ADDED', "Anexo enviado: {$originalName}", null, $userId);
    }

    public function absolutePath(array $attachment): string
    {
        return base_path('storage/uploads/' . $attachment['stored_name']);
    }

    public function listByProcess(int $processId): array
    {
        return $this->attachments->listByProcess($processId);
    }

    public function findById(int $id): ?array
    {
        return $this->attachments->findById($id);
    }

    private function destinationDir(int $processId): string
    {
        $dir = base_path('storage/uploads/' . date('Y') . '/' . date('m') . '/process/' . $processId);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível preparar a pasta de armazenamento.');
        }

        return $dir;
    }

    private function relativePath(int $processId, string $storedName): string
    {
        return date('Y') . '/' . date('m') . '/process/' . $processId . '/' . $storedName;
    }

    private function generateStoredName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}
