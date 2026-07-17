-- Horário de atendimento + Feriados para a contagem do SLA.
--
-- Com o horário de atendimento ligado, o relógio do SLA só corre dentro do
-- horário definido (por dia da semana) e salta os feriados. Ex.: SLA de
-- 30 min aberto às 17h55 (fecham 18h) vence às 09h25 do dia seguinte.
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) Interruptor geral (Configurações → Parâmetros Globais).
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'sla_business_hours_enabled', '0',
       'SLA conta apenas dentro do horário de atendimento e salta feriados (1=sim, 0=24h/dia como antes).'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'sla_business_hours_enabled');

-- 2) Horário por dia da semana (0=Domingo ... 6=Sábado).
--    open_time/close_time NULL = dia fechado (não conta SLA).
CREATE TABLE IF NOT EXISTS tb_business_hours (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    weekday     TINYINT UNSIGNED NOT NULL,
    open_time   TIME NULL,
    close_time  TIME NULL,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by  BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_business_weekday (weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semana-tipo por omissão: seg-sex 09:00–18:00, fim de semana fechado.
INSERT INTO tb_business_hours (weekday, open_time, close_time)
SELECT x.weekday, x.open_time, x.close_time FROM (
    SELECT 0 AS weekday, NULL AS open_time, NULL AS close_time
    UNION ALL SELECT 1, '09:00:00', '18:00:00'
    UNION ALL SELECT 2, '09:00:00', '18:00:00'
    UNION ALL SELECT 3, '09:00:00', '18:00:00'
    UNION ALL SELECT 4, '09:00:00', '18:00:00'
    UNION ALL SELECT 5, '09:00:00', '18:00:00'
    UNION ALL SELECT 6, NULL, NULL
) x
WHERE NOT EXISTS (SELECT 1 FROM tb_business_hours bh WHERE bh.weekday = x.weekday);

-- 3) Feriados (nacionais e regionais). recurring=1 repete todos os anos
--    (usa só mês/dia); recurring=0 é uma data específica (ex.: Páscoa).
CREATE TABLE IF NOT EXISTS tb_holiday (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name        VARCHAR(120) NOT NULL,
    scope       ENUM('NACIONAL','REGIONAL') NOT NULL DEFAULT 'REGIONAL',
    recurring   BOOLEAN NOT NULL DEFAULT 1,
    active      BOOLEAN NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  BIGINT UNSIGNED NULL,
    deleted_at  DATETIME NULL,
    KEY idx_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feriados nacionais fixos de Portugal (repetem todos os anos).
INSERT INTO tb_holiday (holiday_date, name, scope, recurring)
SELECT x.d, x.n, 'NACIONAL', 1 FROM (
    SELECT '2000-01-01' AS d, 'Ano Novo' AS n
    UNION ALL SELECT '2000-04-25', 'Dia da Liberdade'
    UNION ALL SELECT '2000-05-01', 'Dia do Trabalhador'
    UNION ALL SELECT '2000-06-10', 'Dia de Portugal'
    UNION ALL SELECT '2000-08-15', 'Assunção de Nossa Senhora'
    UNION ALL SELECT '2000-10-05', 'Implantação da República'
    UNION ALL SELECT '2000-11-01', 'Todos os Santos'
    UNION ALL SELECT '2000-12-01', 'Restauração da Independência'
    UNION ALL SELECT '2000-12-08', 'Imaculada Conceição'
    UNION ALL SELECT '2000-12-25', 'Natal'
) x
WHERE NOT EXISTS (
    SELECT 1 FROM tb_holiday h
    WHERE h.recurring = 1 AND DATE_FORMAT(h.holiday_date, '%m-%d') = DATE_FORMAT(x.d, '%m-%d')
);

-- Permissão dedicada (Admin/Supervisor por omissão).
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'sla_calendar.manage', 'Configurar Horário de Atendimento e Feriados (SLA)'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'sla_calendar.manage');

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'sla_calendar.manage'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
