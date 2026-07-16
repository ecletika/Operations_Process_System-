<?php

declare(strict_types=1);

namespace App\Modules\Process\Repositories;

use App\Core\Database;
use PDO;

/**
 * Responsável apenas por Base de Dados (OPS-PRD-001 11.8).
 * A tabela mais importante do sistema (TABELA 12 / capítulo 4).
 */
final class ProcessRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * RN-0017 - processo aberto com a mesma matrícula + assunto.
     */
    public function findOpenByVehicleAndSubject(int $vehicleId, int $subjectId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.* FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.vehicle_id = :vehicle_id
              AND p.subject_id = :subject_id
              AND p.deleted_at IS NULL
              AND s.code NOT IN ('SOLVED', 'CLOSED')
            ORDER BY p.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['vehicle_id' => $vehicleId, 'subject_id' => $subjectId]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    /**
     * RN-0021 - Janela de Reincidência: processo encerrado há menos de X dias.
     */
    public function findRecentlyClosedByVehicleAndSubject(int $vehicleId, int $subjectId, int $windowDays): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.* FROM tb_process p
            JOIN tb_status s ON s.id = p.status_id
            WHERE p.vehicle_id = :vehicle_id
              AND p.subject_id = :subject_id
              AND p.deleted_at IS NULL
              AND s.code IN ('SOLVED', 'CLOSED')
              AND p.closed_at IS NOT NULL
              AND p.closed_at >= DATE_SUB(NOW(), INTERVAL :window_days DAY)
            ORDER BY p.closed_at DESC
            LIMIT 1
        ");
        $stmt->execute(['vehicle_id' => $vehicleId, 'subject_id' => $subjectId, 'window_days' => $windowDays]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_process
                (uuid, process_number, company_id, batch_id, customer_id, vehicle_id, subject_id,
                 status_id, priority_id, created_by, first_contact_at, last_contact_at,
                 contact_count, reopen_count, archived, created_at)
            VALUES
                (UUID(), :process_number, :company_id, :batch_id, :customer_id, :vehicle_id, :subject_id,
                 :status_id, :priority_id, :created_by, NOW(), NOW(),
                 1, 0, 0, NOW())
        ');
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.*,
                   c.name AS customer_name, c.phone AS customer_phone,
                   v.plate AS vehicle_plate, v.brand AS vehicle_brand, v.model AS vehicle_model,
                   sub.name AS subject_name,
                   st.code AS status_code, st.name AS status_name,
                   pr.code AS priority_code, pr.name AS priority_name, pr.color AS priority_color,
                   pr.default_sla_minutes,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name,
                   u.last_activity_at AS assigned_last_activity,
                   creator.first_name AS creator_first_name, creator.last_name AS creator_last_name
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            LEFT JOIN tb_user creator ON creator.id = p.created_by
            WHERE p.id = :id AND p.deleted_at IS NULL
        ');
        $stmt->execute(['id' => $id]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    /**
     * RN-0012 - bloqueio transacional (row lock) para evitar dois operadores
     * a assumirem o mesmo processo em simultâneo. Deve ser chamado dentro de
     * uma transação (beginTransaction/commit) pelo Service.
     */
    public function lockForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tb_process WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $process = $stmt->fetch();

        return $process ?: null;
    }

    public function assign(int $id, int $userId, int $statusId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET assigned_to = :assigned_to, status_id = :status_id, assumed_at = NOW(), updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'assigned_to' => $userId, 'updated_by' => $userId, 'status_id' => $statusId]);
    }

    /** Reatribui o processo a outro operador (ação de Admin/Supervisor). */
    public function reassign(int $id, int $newUserId, int $statusId, int $actingUserId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET assigned_to = :assigned_to, status_id = :status_id,
                assumed_at = COALESCE(assumed_at, NOW()),
                updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'assigned_to' => $newUserId, 'status_id' => $statusId, 'updated_by' => $actingUserId]);
    }

    /** Devolve o processo à Fila Inteligente™ (larga o responsável atual). */
    public function releaseToQueue(int $id, int $statusId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET assigned_to = NULL, assumed_at = NULL, status_id = :status_id,
                updated_by = :user_id, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'status_id' => $statusId, 'user_id' => $userId]);
    }

    /**
     * Muda o estado e faz a contabilidade da pausa do SLA:
     *  - a entrar num estado de espera (Aguarda ...) → marca wait_started_at
     *    (o relógio do SLA pára);
     *  - a sair de uma espera → acumula o tempo parado em sla_paused_minutes
     *    e volta a contar.
     */
    public function changeStatus(int $id, int $statusId, int $userId, bool $isWaiting): void
    {
        if ($isWaiting) {
            // Só marca o início da espera se ainda não estiver em pausa,
            // para não perder o tempo já acumulado ao saltar entre esperas.
            $stmt = $this->pdo->prepare('
                UPDATE tb_process
                SET status_id = :status_id,
                    wait_started_at = COALESCE(wait_started_at, NOW()),
                    updated_by = :user_id, updated_at = NOW()
                WHERE id = :id
            ');
        } else {
            $stmt = $this->pdo->prepare('
                UPDATE tb_process
                SET status_id = :status_id,
                    sla_paused_minutes = sla_paused_minutes + IFNULL(TIMESTAMPDIFF(MINUTE, wait_started_at, NOW()), 0),
                    wait_started_at = NULL,
                    updated_by = :user_id, updated_at = NOW()
                WHERE id = :id
            ');
        }

        $stmt->execute(['id' => $id, 'status_id' => $statusId, 'user_id' => $userId]);
    }

    public function close(int $id, int $statusId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET status_id = :status_id, closed_at = NOW(), closed_by = :closed_by, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'status_id' => $statusId, 'closed_by' => $userId, 'updated_by' => $userId]);
    }

    public function reopen(int $id, int $statusId, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET status_id = :status_id, reopen_count = reopen_count + 1,
                assigned_to = NULL, assumed_at = NULL,
                updated_by = :user_id, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'status_id' => $statusId, 'user_id' => $userId]);
    }

    /**
     * Exclusão de Processo - restrita a Administrador (permissão process.delete).
     * Continua a ser soft delete (RN-0048); o registo fica preservado na base
     * de dados para auditoria, apenas deixa de aparecer em qualquer listagem.
     */
    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process SET deleted_at = NOW(), deleted_by = :user_id WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /** Arquiva/desarquiva um processo manualmente. */
    public function setArchived(int $id, bool $archived, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET archived = :archived, archived_at = :archived_at, updated_by = :user_id, updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $id,
            'archived' => $archived ? 1 : 0,
            'archived_at' => $archived ? date('Y-m-d H:i:s') : null,
            'user_id' => $userId,
        ]);
    }

    /**
     * Arquiva automaticamente os processos concluídos (SOLVED/CLOSED) há mais
     * de $days dias e que ainda não estão arquivados. Devolve o total.
     */
    public function autoArchiveConcluded(int $days): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE tb_process p
            JOIN tb_status s ON s.id = p.status_id
            SET p.archived = 1, p.archived_at = NOW()
            WHERE p.deleted_at IS NULL
              AND p.archived = 0
              AND s.code IN ('SOLVED', 'CLOSED')
              AND p.closed_at IS NOT NULL
              AND p.closed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    /**
     * Exclui (soft-delete → Lixeira) os processos arquivados há mais de $days
     * dias. Continuam recuperáveis na Lixeira. Devolve o total.
     */
    public function autoDeleteArchived(int $days): int
    {
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET deleted_at = NOW()
            WHERE deleted_at IS NULL
              AND archived = 1
              AND archived_at IS NOT NULL
              AND archived_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ');
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    /**
     * RN-0014/0015 - toda interação incrementa contact_count e atualiza last_contact_at.
     */
    public function registerContact(int $id): void
    {
        // O prazo do SLA reinicia a cada interação (decisão do cliente): como
        // passa a contar a partir de agora, a pausa acumulada até aqui deixa
        // de ser relevante e é zerada. Se o processo estiver em espera, o
        // relógio continua parado, mas a contar a partir deste momento.
        $stmt = $this->pdo->prepare('
            UPDATE tb_process
            SET contact_count = contact_count + 1,
                last_contact_at = NOW(),
                sla_paused_minutes = 0,
                wait_started_at = IF(wait_started_at IS NULL, NULL, NOW()),
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Fila Inteligente™ - processos ainda sem responsável.
     *
     * Isolamento por departamento (RN-0011): $allowedBatchIds define o que o
     * operador pode ver/assumir.
     *   - null  → sem restrição (Supervisor/Admin ou "ver todos os lotes");
     *   - [ids] → só processos destes lotes (o departamento do operador);
     *   - []    → não vê nada (operador sem lotes atribuídos — caso-limite).
     *
     * @param array<int>|null $allowedBatchIds
     */
    public function listQueue(?array $allowedBatchIds = null): array
    {
        $sql = "
            SELECT p.*, v.plate AS vehicle_plate, c.name AS customer_name,
                   sub.name AS subject_name, pr.code AS priority_code, pr.name AS priority_name, pr.color AS priority_color, pr.default_sla_minutes,
                   creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                   br.name AS branch_name, d.name AS department_name
            FROM tb_process p
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user creator ON creator.id = p.created_by
            LEFT JOIN tb_batch bt ON bt.id = p.batch_id
            LEFT JOIN tb_department d ON d.id = bt.department_id
            LEFT JOIN tb_branch br ON br.id = d.branch_id
            WHERE p.deleted_at IS NULL AND st.code = 'QUEUE'
        ";

        $params = [];
        if ($allowedBatchIds !== null) {
            if ($allowedBatchIds === []) {
                return []; // operador sem lotes — não vê nenhum processo
            }
            $placeholders = [];
            foreach (array_values($allowedBatchIds) as $i => $batchId) {
                $key = "batch_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = (int) $batchId;
            }
            $sql .= ' AND p.batch_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ' ORDER BY pr.sort_order ASC, p.created_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Meus Processos - operador vê apenas o que lhe está atribuído (3.9).
     *
     * $archived=false → caixa "em curso" (não concluídos);
     * $archived=true  → "Caixa de Entrada Arquivada" (Resolvidos/Encerrados) (#10).
     */
    public function listAssignedTo(int $userId, bool $archived = false): array
    {
        $statusFilter = $archived ? "st.code IN ('SOLVED', 'CLOSED')" : "st.code NOT IN ('SOLVED', 'CLOSED')";

        $stmt = $this->pdo->prepare("
            SELECT p.*, v.plate AS vehicle_plate, c.name AS customer_name,
                   sub.name AS subject_name, st.code AS status_code, st.name AS status_name,
                   pr.code AS priority_code, pr.name AS priority_name, pr.color AS priority_color, pr.default_sla_minutes
            FROM tb_process p
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL AND p.assigned_to = :user_id AND {$statusFilter}
            ORDER BY p.last_contact_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Processos Criados por mim — o criador acompanha (e pode interagir),
     * mesmo que outro operador os tenha assumido.
     */
    public function listCreatedBy(int $userId, bool $archived = false): array
    {
        $statusFilter = $archived ? "st.code IN ('SOLVED', 'CLOSED')" : "st.code NOT IN ('SOLVED', 'CLOSED')";

        $stmt = $this->pdo->prepare("
            SELECT p.*, v.plate AS vehicle_plate, c.name AS customer_name,
                   sub.name AS subject_name, st.code AS status_code, st.name AS status_name,
                   pr.code AS priority_code, pr.name AS priority_name, pr.color AS priority_color, pr.default_sla_minutes,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name,
                   u.last_activity_at AS assigned_last_activity
            FROM tb_process p
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            WHERE p.deleted_at IS NULL AND p.created_by = :user_id AND {$statusFilter}
            ORDER BY p.last_contact_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function statusIdByCode(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tb_status WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException("Estado '{$code}' não existe. Execute database/009_seeders.sql.");
        }

        return (int) $id;
    }

    /** Nome legível de um estado (ex.: WAIT_CLIENT → "Aguarda Cliente"). */
    public function statusNameByCode(string $code): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM tb_status WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : $code;
    }

    public function statusCodeById(int $statusId): string
    {
        $stmt = $this->pdo->prepare('SELECT code FROM tb_status WHERE id = :id');
        $stmt->execute(['id' => $statusId]);
        $code = $stmt->fetchColumn();

        if ($code === false) {
            throw new \RuntimeException("Estado #{$statusId} não existe.");
        }

        return (string) $code;
    }

    /**
     * RF-0036 / RF-0037 - Pesquisa Global com normalização automática:
     * "AA12BB" encontra "AA-12-BB", "351912345678" encontra "+351 912 345 678".
     * Usa REGEXP_REPLACE (MySQL 8) para comparar apenas dígitos/letras,
     * independentemente de como o utilizador escreveu.
     *
     * $normalizedAlnum e $normalizedDigits só entram na pesquisa quando não
     * vazios, para evitar que um termo puramente textual (ex.: "João", sem
     * dígitos) faça `LIKE '%%'` contra a matrícula/telefone e devolva tudo.
     */
    public function search(string $rawQuery, string $normalizedAlnum, string $normalizedDigits, int $limit = 30): array
    {
        $conditions = ['c.name LIKE :raw1', 'sub.name LIKE :raw2', "CONCAT(u.first_name, ' ', u.last_name) LIKE :raw3"];
        $params = ['raw1' => "%{$rawQuery}%", 'raw2' => "%{$rawQuery}%", 'raw3' => "%{$rawQuery}%"];

        if ($normalizedAlnum !== '') {
            $conditions[] = "REGEXP_REPLACE(p.process_number, '[^0-9A-Za-z]', '') LIKE :alnum1";
            $conditions[] = "REGEXP_REPLACE(v.plate, '[^0-9A-Za-z]', '') LIKE :alnum2";
            $params['alnum1'] = "%{$normalizedAlnum}%";
            $params['alnum2'] = "%{$normalizedAlnum}%";
        }

        if ($normalizedDigits !== '') {
            $conditions[] = "REGEXP_REPLACE(c.phone, '[^0-9]', '') LIKE :digits";
            $params['digits'] = "%{$normalizedDigits}%";
        }

        $sql = '
            SELECT p.id, p.process_number, p.created_at,
                   c.name AS customer_name, c.phone AS customer_phone,
                   v.plate AS vehicle_plate,
                   sub.name AS subject_name,
                   st.code AS status_code, st.name AS status_name,
                   pr.name AS priority_name, pr.color AS priority_color,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            WHERE p.deleted_at IS NULL AND (' . implode(' OR ', $conditions) . ')
            ORDER BY p.created_at DESC
            LIMIT :limit
        ';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Visão "Todos os Processos" para Admin/Supervisor (permissão
     * process.view_all) - abas + filtros combináveis.
     *
     * $filters aceita: tab ('in_progress'|'finished'|'no_interaction'|'all'),
     * status_id, batch_id, priority_id, subject_id, assigned_to,
     * date_from, date_to (sobre created_at).
     */
    public function filterAll(array $filters, int $limit = 300): array
    {
        $conditions = ['p.deleted_at IS NULL'];
        $params = [];

        $tab = $filters['tab'] ?? 'all';

        // Os processos arquivados só aparecem na aba "Arquivados" (e em "Todos");
        // ficam fora das restantes para não poluírem as listas ativas.
        if (!in_array($tab, ['arquivados', 'all'], true)) {
            $conditions[] = 'p.archived = 0';
        }

        switch ($tab) {
            case 'in_progress': // Abertos (qualquer estado que não seja concluído)
                $conditions[] = "st.code NOT IN ('SOLVED', 'CLOSED')";
                break;
            case 'em_tratamento':
                $conditions[] = "st.code = 'IN_PROGRESS'";
                break;
            case 'em_espera':
                $conditions[] = "st.code IN ('WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL')";
                break;
            case 'resolvidos':
                $conditions[] = "st.code = 'SOLVED'";
                break;
            case 'encerrados':
                $conditions[] = "st.code = 'CLOSED'";
                break;
            case 'reabertos':
                $conditions[] = 'p.reopen_count > 0';
                break;
            case 'arquivados':
                $conditions[] = 'p.archived = 1';
                break;
            case 'finished': // Concluídos (resolvidos + encerrados)
                $conditions[] = "st.code IN ('SOLVED', 'CLOSED')";
                break;
            case 'no_interaction':
                $conditions[] = 'p.contact_count <= 1';
                break;
            case 'all':
            default:
                break;
        }

        // Filtros combináveis que aceitam um OU vários valores (multi-seleção).
        // Aceita tanto escalar ('5') como lista (['5','7']) e monta um IN (...).
        $inClause = function (string $column, mixed $value, string $prefix) use (&$conditions, &$params): void {
            $values = array_values(array_unique(array_filter(
                array_map('intval', (array) $value),
                static fn (int $v): bool => $v > 0
            )));
            if ($values === []) {
                return;
            }
            $placeholders = [];
            foreach ($values as $i => $v) {
                $key = $prefix . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $v;
            }
            $conditions[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
        };

        $inClause('p.status_id', $filters['status_id'] ?? [], 'status_');
        $inClause('p.batch_id', $filters['batch_id'] ?? [], 'batch_');
        $inClause('p.priority_id', $filters['priority_id'] ?? [], 'prio_');
        $inClause('p.subject_id', $filters['subject_id'] ?? [], 'subj_');
        $inClause('p.assigned_to', $filters['assigned_to'] ?? [], 'assg_');

        if (!empty($filters['date_from'])) {
            $conditions[] = 'p.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'p.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = '
            SELECT p.id, p.process_number, p.contact_count, p.reopen_count, p.created_at, p.closed_at,
                   p.last_contact_at, p.sla_paused_minutes, p.wait_started_at,
                   c.name AS customer_name, v.plate AS vehicle_plate,
                   sub.name AS subject_name,
                   st.code AS status_code, st.name AS status_name,
                   pr.name AS priority_name, pr.color AS priority_color, pr.default_sla_minutes,
                   u.first_name AS assigned_first_name, u.last_name AS assigned_last_name,
                   u.last_activity_at AS assigned_last_activity,
                   creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                   br.name AS branch_name, d.name AS department_name
            FROM tb_process p
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_vehicle v ON v.id = p.vehicle_id
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            LEFT JOIN tb_user u ON u.id = p.assigned_to
            LEFT JOIN tb_user creator ON creator.id = p.created_by
            LEFT JOIN tb_batch bt ON bt.id = p.batch_id
            LEFT JOIN tb_department d ON d.id = bt.department_id
            LEFT JOIN tb_branch br ON br.id = d.branch_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY p.created_at DESC
            LIMIT :limit
        ';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
