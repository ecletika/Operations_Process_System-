<?php

declare(strict_types=1);

namespace App\Modules\Intelligence\Services;

use App\Core\Database;
use App\Core\Settings;
use App\Modules\Notification\Services\NotificationService;
use PDO;

/**
 * OPS-PRD-001 §7.18 - Regras de Negócio Inteligentes (RN-0056 a RN-0060).
 * Pensado para ser corrido periodicamente (ver database/run_intelligence.php).
 */
final class IntelligenceService
{
    private PDO $pdo;

    public function __construct(
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
        $this->pdo = Database::connection();
    }

    /**
     * RN-0056 - Processo Esquecido: sem qualquer evento na Timeline Viva™
     * durante X horas (configurável). Notifica responsável + supervisores.
     */
    public function detectForgottenProcesses(): int
    {
        $hours = (int) Settings::get('forgotten_process_hours', 48);

        $stmt = $this->pdo->prepare("
            SELECT p.id, p.process_number, p.company_id, p.assigned_to,
                   MAX(t.created_at) AS last_activity
            FROM tb_process p
            LEFT JOIN tb_timeline t ON t.process_id = p.id AND t.deleted_at IS NULL
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.deleted_at IS NULL AND s.code NOT IN ('SOLVED', 'CLOSED')
            GROUP BY p.id, p.process_number, p.company_id, p.assigned_to, p.created_at
            HAVING COALESCE(MAX(t.created_at), p.created_at) < DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ");
        $stmt->execute(['hours' => $hours]);
        $forgotten = $stmt->fetchAll();

        foreach ($forgotten as $process) {
            $title = "Processo esquecido: {$process['process_number']}";
            $message = "Sem qualquer atualização há mais de {$hours} horas.";

            if ($process['assigned_to'] !== null) {
                $this->notifications->notifyOnce((int) $process['assigned_to'], $title, $message, 'WARNING', $hours * 60);
            }

            $this->notifications->notifySupervisors((int) $process['company_id'], $title, $message, 'WARNING');
        }

        return count($forgotten);
    }

    /**
     * RF-0039 - SLA Próximo: avisa o operador antes do SLA vencer.
     */
    public function detectSlaNear(int $minutesBeforeDeadline = 15): int
    {
        // O filtro de "falta pouco" é feito em PHP: com o horário de atendimento
        // ligado o relógio está parado fora de expediente, e o aviso em SQL
        // disparava de madrugada por causa do TIMESTAMPDIFF.
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.process_number, p.assigned_to, p.created_at, p.closed_at,
                   p.sla_paused_total_minutes, p.sla_closed_minutes, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL
              AND s.code NOT IN ('SOLVED', 'CLOSED')
              AND p.assigned_to IS NOT NULL
              AND pr.default_sla_minutes IS NOT NULL
        ");
        $stmt->execute();

        $agora = time();
        $avisados = 0;

        foreach ($stmt->fetchAll() as $row) {
            $remaining = (int) $row['default_sla_minutes'] - sla_process_minutes($row);
            if ($remaining < 0 || $remaining > $minutesBeforeDeadline) {
                continue;
            }

            $title = "SLA próximo: {$row['process_number']}";
            $message = "O SLA vence em aproximadamente {$remaining} minutos.";

            $this->notifications->notifyOnce((int) $row['assigned_to'], $title, $message, 'WARNING', $minutesBeforeDeadline);
            $avisados++;
        }

        return $avisados;
    }

    /**
     * Lembrete de Próximo Contacto: quando a data/hora agendada (Imobilizados
     * + Baixa, por omissão) chega, avisa o responsável para ligar ao cliente.
     * Repete-se a cada next_contact_reminder_repeat_minutes enquanto o
     * contacto não for feito (registar um contacto limpa/reagenda a data).
     */
    public function detectNextContactDue(): int
    {
        $repeatMinutes = (int) Settings::get('next_contact_reminder_repeat_minutes', 60);

        $stmt = $this->pdo->prepare("
            SELECT p.id, p.process_number, p.assigned_to, c.name AS customer_name, c.phone AS customer_phone
            FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            JOIN tb_customer c ON c.id = p.customer_id
            WHERE p.deleted_at IS NULL
              AND s.code NOT IN ('SOLVED', 'CLOSED')
              AND p.assigned_to IS NOT NULL
              AND p.next_contact_at IS NOT NULL
              AND p.next_contact_at <= NOW()
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $title = "📞 Ligar ao cliente: {$row['process_number']}";
            $message = "Próximo Contacto vencido — {$row['customer_name']}" . ($row['customer_phone'] ? " ({$row['customer_phone']})" : '') . '.';

            $this->notifications->notifyOnce(
                (int) $row['assigned_to'],
                $title,
                $message,
                'WARNING',
                $repeatMinutes,
                '/processes/' . (int) $row['id']
            );
        }

        return count($rows);
    }

    /**
     * RN-0059 - Cliente Frequente: muitos processos no período configurado.
     */
    public function isFrequentCustomer(int $customerId): bool
    {
        $threshold = (int) Settings::get('frequent_customer_threshold', 5);
        $windowDays = (int) Settings::get('recurrence_window_days', 90);

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM tb_process
            WHERE deleted_at IS NULL AND customer_id = :customer_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
        ');
        $stmt->execute(['customer_id' => $customerId, 'window_days' => $windowDays]);

        return ((int) $stmt->fetchColumn()) >= $threshold;
    }

    /**
     * RN-0060 - Viatura Recorrente: mesma matrícula com vários processos recentes.
     */
    public function isRecurrentVehicle(int $vehicleId): bool
    {
        $threshold = (int) Settings::get('recurrent_vehicle_threshold', 3);
        $windowDays = (int) Settings::get('recurrence_window_days', 90);

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM tb_process
            WHERE deleted_at IS NULL AND vehicle_id = :vehicle_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
        ');
        $stmt->execute(['vehicle_id' => $vehicleId, 'window_days' => $windowDays]);

        return ((int) $stmt->fetchColumn()) >= $threshold;
    }
}
