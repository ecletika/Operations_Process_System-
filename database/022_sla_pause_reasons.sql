-- Motivos de Pausa do SLA configuráveis (como os Assuntos).
--
-- Os motivos passam a ser estados marcados com is_waiting=1 em tb_status:
-- reaproveitamos os 4 que já existiam (Aguarda Cliente/Peças/Oficina/
-- Terceiros) e o Administrador pode criar outros (ex.: "Aguarda Seguradora")
-- na tela Configurações → Motivos de Pausa do SLA, sem tocar em código.
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) Marca quais os estados que param o relógio do SLA.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_status' AND column_name = 'is_waiting');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_status ADD COLUMN is_waiting BOOLEAN NOT NULL DEFAULT 0 AFTER sort_order',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Os 4 motivos que já existiam passam a estar marcados como tal.
UPDATE tb_status
SET is_waiting = 1
WHERE code IN ('WAIT_CLIENT', 'WAIT_PARTS', 'WAIT_WORKSHOP', 'WAIT_EXTERNAL');

-- 3) Permissão dedicada e atribuível para gerir os motivos.
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'sla_reasons.manage', 'Configurar Motivos de Pausa do SLA'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'sla_reasons.manage');

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'sla_reasons.manage'
WHERE r.code = 'ROLE_ADMIN'
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
