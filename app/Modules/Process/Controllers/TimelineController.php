<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Process\Repositories\TimelineRepository;

/**
 * Timeline Global™ (OPS-UI-001 · 📝): últimos acontecimentos de todos os
 * processos. A Timeline do Processo e o Events Replay™ vivem no detalhe de
 * cada processo; a Auditoria Visual em /admin/audit.
 */
final class TimelineController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('Process/Views/timeline_global', [
            'entries' => (new TimelineRepository())->listGlobal(150),
        ]);
    }
}
