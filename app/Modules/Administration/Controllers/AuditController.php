<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Administration\Repositories\AuditRepository;

/**
 * RF-0027 - Módulo 09 (Auditoria). Só leitura: tb_audit nunca é editada
 * nem apagada (RN-0038/0039).
 */
final class AuditController extends Controller
{
    public function index(Request $request): never
    {
        $repository = new AuditRepository();
        $tableName = (string) $request->input('table_name', '');

        $this->view('Administration/Views/audit', [
            'logs' => $repository->list($tableName !== '' ? $tableName : null, null),
            'tables' => $repository->distinctTables(),
            'selectedTable' => $tableName,
        ]);
    }
}
