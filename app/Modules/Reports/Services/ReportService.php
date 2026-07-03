<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Core\Database;
use PDO;

/**
 * RF-0041 a RF-0043 - Exportação. V1 entrega CSV (Excel/PDF ficam para
 * a Fase 4, quando o motor de relatórios avançados for construído).
 */
final class ReportService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function processesBetween(?string $from, ?string $to): array
    {
        $sql = 'SELECT * FROM vw_process_summary WHERE 1=1';
        $params = [];

        if ($from !== null && $from !== '') {
            $sql .= ' AND created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }

        if ($to !== null && $to !== '') {
            $sql .= ' AND created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
