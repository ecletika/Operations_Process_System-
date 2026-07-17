<?php

declare(strict_types=1);

namespace App\Modules\Administration\Repositories;

use App\Core\Database;
use PDO;

/**
 * Horário de atendimento (tb_business_hours) e Feriados (tb_holiday) usados
 * pela contagem do SLA em horário útil (ver BusinessClock).
 */
final class SlaCalendarRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<int, array<string, mixed>> indexado por weekday (0=Dom) */
    public function hours(): array
    {
        $rows = $this->pdo->query('SELECT weekday, open_time, close_time FROM tb_business_hours ORDER BY weekday')->fetchAll();
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(int) $row['weekday']] = $row;
        }

        return $byDay;
    }

    public function setDay(int $weekday, ?string $open, ?string $close, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_business_hours (weekday, open_time, close_time, updated_by)
            VALUES (:weekday, :open, :close, :user_id)
            ON DUPLICATE KEY UPDATE open_time = VALUES(open_time), close_time = VALUES(close_time),
                                    updated_by = VALUES(updated_by), updated_at = NOW()
        ');
        $stmt->execute([
            'weekday' => $weekday,
            'open' => $open !== null && $open !== '' ? $open : null,
            'close' => $close !== null && $close !== '' ? $close : null,
            'user_id' => $userId,
        ]);
    }

    /** @return array<int, array<string, mixed>> feriados ativos, ordenados por mês/dia */
    public function holidays(): array
    {
        return $this->pdo->query("
            SELECT * FROM tb_holiday
            WHERE deleted_at IS NULL
            ORDER BY scope DESC, DATE_FORMAT(holiday_date, '%m-%d') ASC
        ")->fetchAll();
    }

    public function addHoliday(string $date, string $name, string $scope, bool $recurring, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_holiday (holiday_date, name, scope, recurring, active, created_at, created_by)
            VALUES (:date, :name, :scope, :recurring, 1, NOW(), :user_id)
        ');
        $stmt->execute([
            'date' => $date,
            'name' => $name,
            'scope' => $scope === 'NACIONAL' ? 'NACIONAL' : 'REGIONAL',
            'recurring' => $recurring ? 1 : 0,
            'user_id' => $userId,
        ]);
    }

    /** Soft-delete de um feriado. */
    public function deleteHoliday(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_holiday SET deleted_at = NOW(), active = 0 WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
    }
}
