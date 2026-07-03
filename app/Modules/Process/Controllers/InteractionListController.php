<?php

declare(strict_types=1);

namespace App\Modules\Process\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Process\Repositories\InteractionRepository;

/**
 * Módulo Interações (OPS-UI-001 · 💬): Lista de Interações, Contactos
 * Telefónicos, Emails e Histórico de Contactos — tudo na mesma página,
 * com filtros. "Nova Interação" regista-se dentro do processo (RF-0016).
 */
final class InteractionListController extends Controller
{
    public function index(Request $request): never
    {
        $repository = new InteractionRepository();

        $filters = [
            'channel' => (string) $request->input('channel', ''),
            'operator_id' => (string) $request->input('operator_id', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        $this->view('Process/Views/interactions', [
            'interactions' => $repository->filterAll($filters),
            'channels' => $repository->distinctChannels(),
            'operators' => (new UserRepository())->listAll(),
            'filters' => $filters,
        ]);
    }
}
