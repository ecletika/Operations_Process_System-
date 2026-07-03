<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Env;
use PDO;
use Throwable;

/**
 * 🔧 Ferramentas · Sistema & Monitorização - diagnóstico só de leitura,
 * útil em qualquer alojamento (não depende do cPanel). Backup/Importação
 * reais devem ser feitos pelas ferramentas do alojamento (ex.: cPanel).
 */
final class ToolsController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('Administration/Views/tools', [
            'php' => $this->phpInfo(),
            'db' => $this->dbInfo(),
            'extensions' => $this->extensions(),
            'storage' => $this->storageInfo(),
            'migrations' => $this->migrations(),
        ]);
    }

    private function phpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'os' => PHP_OS,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? '—',
            'timezone' => date_default_timezone_get(),
            'memory_limit' => (string) ini_get('memory_limit'),
            'upload_max' => (string) ini_get('upload_max_filesize'),
            'post_max' => (string) ini_get('post_max_size'),
            'debug' => (bool) Env::get('APP_DEBUG', false),
        ];
    }

    private function dbInfo(): array
    {
        try {
            $pdo = Database::connection();
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = (int) $pdo->query('
                SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
            ')->fetchColumn();
            $name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

            return ['ok' => true, 'version' => $version, 'tables' => $tables, 'name' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Extensões PHP de que o sistema depende. */
    private function extensions(): array
    {
        $required = ['pdo_mysql', 'mbstring', 'fileinfo', 'json', 'openssl'];
        $result = [];
        foreach ($required as $ext) {
            $result[$ext] = extension_loaded($ext);
        }

        return $result;
    }

    private function storageInfo(): array
    {
        $uploads = base_path('storage/uploads');
        $logs = base_path('storage/logs');

        return [
            'uploads_size' => $this->dirSize($uploads),
            'uploads_writable' => is_writable($uploads),
            'logs_size' => $this->dirSize($logs),
            'logs_writable' => is_writable($logs),
            'log_files' => is_dir($logs) ? count(glob($logs . '/*.log') ?: []) : 0,
        ];
    }

    /** Ficheiros SQL/PHP de instalação em database/ (informativo). */
    private function migrations(): array
    {
        $dir = base_path('database');
        $files = glob($dir . '/*.{sql,php}', GLOB_BRACE) ?: [];
        sort($files);

        return array_map(static fn (string $f): string => basename($f), $files);
    }

    private function dirSize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $total = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += $file->getSize();
            }
        }

        return $total;
    }
}
