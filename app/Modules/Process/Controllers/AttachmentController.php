<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Process\Services\AttachmentService;
use RuntimeException;

/**
 * RF-0025 (upload) / RF-0026 (pré-visualização).
 */
final class AttachmentController extends Controller
{
    private const PREVIEWABLE_MIME_PREFIXES = ['image/', 'application/pdf'];

    public function store(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Session::flash('errors', ['Sessão expirada, tente novamente.']);
            Response::redirect('/processes/' . $processId);
        }

        try {
            (new AttachmentService())->upload($processId, $_FILES['file'] ?? [], (int) Session::get('user_id'));
            Session::flash('success', 'Anexo enviado com sucesso.');
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        Response::redirect('/processes/' . $processId);
    }

    /**
     * RF-0026 - pré-visualiza imagens/PDF inline, outros tipos como download.
     */
    public function download(Request $request, array $params): never
    {
        $service = new AttachmentService();
        $attachment = $service->findById((int) $params['id']);

        if ($attachment === null) {
            http_response_code(404);
            echo 'Anexo não encontrado.';
            exit;
        }

        $path = $service->absolutePath($attachment);

        if (!is_file($path)) {
            http_response_code(404);
            echo 'Ficheiro não encontrado no armazenamento.';
            exit;
        }

        $isPreviewable = false;
        foreach (self::PREVIEWABLE_MIME_PREFIXES as $prefix) {
            if (str_starts_with((string) $attachment['mime_type'], $prefix)) {
                $isPreviewable = true;
                break;
            }
        }

        $disposition = $isPreviewable ? 'inline' : 'attachment';

        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($attachment['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }
}
