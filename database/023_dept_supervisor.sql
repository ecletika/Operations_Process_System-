-- Novo perfil "Supervisor de Departamento".
--
-- Vê os processos de toda a sua Filial (ou de departamentos escolhidos) em
-- "Todos os Processos", mas só pode assumir/reatribuir os que estão na fila
-- do SEU departamento. Na Fila Inteligente continua a ver só o seu.
--
-- A visibilidade é configurada na ficha do Utilizador:
--   view_scope = 'OWN'    → só o seu departamento (comportamento normal)
--                'BRANCH' → todos os departamentos da sua Filial
--                'CUSTOM' → só os departamentos escolhidos (tb_user_view_department)
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) Âmbito de visibilidade na ficha do utilizador.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_user' AND column_name = 'view_scope');
SET @s := IF(@c = 0,
  "ALTER TABLE tb_user ADD COLUMN view_scope ENUM('OWN','BRANCH','CUSTOM') NOT NULL DEFAULT 'OWN' AFTER view_all_batches",
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Departamentos escolhidos (só usados quando view_scope = 'CUSTOM').
CREATE TABLE IF NOT EXISTS tb_user_view_department (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by    BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_user_view_dept (user_id, department_id),
    KEY idx_uvd_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Permissão: ver os processos da sua filial/departamentos, SEM poder
--    assumir/reatribuir fora do seu departamento.
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.view_branch',
       'Ver os processos da sua Filial/departamentos escolhidos (sem assumir/reatribuir fora do seu departamento)'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'process.view_branch');

-- 4) O novo perfil.
INSERT INTO tb_role (uuid, code, name, active)
SELECT UUID(), 'ROLE_DEPT_SUPERVISOR', 'Supervisor de Departamento', 1
WHERE NOT EXISTS (SELECT 1 FROM tb_role WHERE code = 'ROLE_DEPT_SUPERVISOR');

-- 5) Permissões do novo perfil: trabalha como um operador no seu
--    departamento + vê a filial + muda estados (pausa do SLA) + relatórios.
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code IN (
    'dashboard.view',
    'process.create',
    'process.assume',
    'process.edit_own',
    'process.close',
    'process.reopen',
    'process.change_status',
    'process.view_branch',
    'reports.export'
)
WHERE r.code = 'ROLE_DEPT_SUPERVISOR'
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
