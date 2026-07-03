<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;

/**
 * RF-0050 - Módulo 16 (Segurança) - consulta dos ficheiros em storage/logs/.
 */
final class LogController extends Controller
{
    private const MAX_LINES = 300;

    public function index(Request $request): never
    {
        $dir = base_path('storage/logs');
        $files = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..', '.gitkeep'])) : [];
        sort($files);

        $selected = (string) $request->input('file', $files[0] ?? '');
        $lines = [];

        if ($selected !== '' && in_array($selected, $files, true)) {
            $path = $dir . '/' . $selected;
            $all = file($path, FILE_IGNORE_NEW_LINES) ?: [];
            $lines = array_slice($all, -self::MAX_LINES);
            $lines = array_reverse($lines);
        }

        $this->view('Administration/Views/logs', [
            'files' => $files,
            'selected' => $selected,
            'lines' => $lines,
        ]);
    }
}
