<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Administration\Services\CustomerImportService;
use App\Modules\Administration\Services\SpreadsheetReader;
use RuntimeException;

/**
 * 📥 Importação (Configurações) — subir Excel/CSV para popular Clientes
 * em massa. Nunca importa fisicamente do $_FILES para a base de dados sem
 * passar pelo mesmo Service/Repository usado no resto do sistema.
 */
final class ImportController extends Controller
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function index(Request $request): never
    {
        $this->view('Administration/Views/import', [
            'result' => Session::pullFlash('import_result'),
            'errors' => Session::pullFlash('errors', []),
        ]);
    }

    public function importCustomers(Request $request): never
    {
        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/admin/import');
        }

        $file = $_FILES['file'] ?? null;

        try {
            $this->validateUpload($file);

            $rows = SpreadsheetReader::read($file['tmp_name'], $file['name']);
            $result = (new CustomerImportService())->import($rows, (int) Session::get('user_id'));

            Session::flash('import_result', ['type' => 'Clientes', 'stats' => $result]);
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        Response::redirect('/admin/import');
    }

    /** @param array{tmp_name?:string, name?:string, error?:int, size?:int}|null $file */
    private function validateUpload(?array $file): void
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Escolha um ficheiro .xlsx ou .csv para enviar.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no envio do ficheiro (código ' . $file['error'] . '). Tente novamente.');
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('Ficheiro demasiado grande (máx. 10 MB).');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Envio inválido.');
        }
    }
}
