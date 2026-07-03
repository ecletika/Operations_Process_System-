<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Modules\Intelligence\Repositories\InsightRepository;

/**
 * 📈 Inteligência Operacional™ - Dashboard Executivo (OPS-PRD-001 §7.18).
 * Só leitura: KPIs, tendências, gargalos, processos críticos, clientes
 * frequentes e viaturas recorrentes. Acesso Admin/Supervisor (reports.export).
 */
final class IntelligenceController extends Controller
{
    public function index(Request $request): never
    {
        $from = (string) $request->input('from', '') !== '' ? (string) $request->input('from') : null;
        $to = (string) $request->input('to', '') !== '' ? (string) $request->input('to') : null;

        $insights = new InsightRepository();

        $this->view('Intelligence/Views/dashboard', [
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'kpis' => $insights->kpis($from, $to),
            'trend' => $insights->dailyTrend(14),
            'bottlenecks' => $insights->bottlenecks(),
            'bySubject' => $insights->bySubject($from, $to),
            'critical' => $insights->criticalOpen(),
            'frequentCustomers' => $insights->frequentCustomers(),
            'recurrentVehicles' => $insights->recurrentVehicles(),
        ]);
    }
}
