<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Modules\Process\Repositories\ProcessRepository;
use App\Modules\Process\Services\NoteService;
use App\Modules\Process\Support\BatchScope;
use RuntimeException;

/**
 * RF-0023 / RF-0024 - Observações internas do Processo.
 */
final class NoteController extends Controller
{
    public function store(Request $request, array $params): never
    {
        $processId = (int) $params['id'];

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Response::redirect('/processes/' . $processId);
        }

        // Observações contam como contacto (atualizam o Último Contacto e
        // renovam o SLA), por isso só o departamento do processo, chefias ou
        // o criador as podem adicionar — quem só consulta, não.
        $process = (new ProcessRepository())->findById($processId);
        if ($process === null || !BatchScope::canContribute($process)) {
            Session::flash('errors', ['Este processo é de outro departamento; só o pode consultar.']);
            Response::redirect('/processes/' . $processId);
        }

        $text = trim((string) $request->input('note', ''));

        if ($text !== '') {
            (new NoteService())->add($processId, $text, (int) Session::get('user_id'));
        }

        Response::redirect('/processes/' . $processId);
    }

    public function update(Request $request, array $params): never
    {
        $noteId = (int) $params['id'];
        $processId = (int) $request->input('process_id', 0);

        if (!Session::verifyCsrfToken($request->input('_csrf'))) {
            Response::redirect('/processes/' . $processId);
        }

        $text = trim((string) $request->input('note', ''));

        try {
            if ($text !== '') {
                (new NoteService())->edit($noteId, $text, (int) Session::get('user_id'));
            }
        } catch (RuntimeException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        Response::redirect('/processes/' . $processId);
    }
}
