-- SLA justo: (1) cada interação reinicia o prazo do SLA e (2) o relógio fica
-- em pausa enquanto o processo aguarda Cliente/Peças/Oficina/Terceiros — o
-- operador deixa de ser penalizado por demoras que não dependem dele.
--
-- O "Tempo Total" (espera real do cliente) continua a ser calculado a partir
-- de created_at e NÃO é afetado por isto — a gestão continua a ver a verdade.
--
-- Idempotente: pode correr-se mais do que uma vez.
SET NAMES utf8mb4;

-- 1) Contabilidade da pausa do SLA no processo.
--    sla_paused_minutes: minutos já acumulados em pausa desde o último contacto.
--    wait_started_at   : quando entrou no estado de espera atual (NULL = a contar).
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process' AND column_name = 'sla_paused_minutes');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN sla_paused_minutes INT NOT NULL DEFAULT 0 AFTER contact_count',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process' AND column_name = 'wait_started_at');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN wait_started_at DATETIME NULL AFTER sla_paused_minutes',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Permissão dedicada e atribuível para mudar o estado do processo
--    (Aguarda Cliente/Peças/Oficina/Terceiros e retomar tratamento).
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.change_status', 'Mudar o estado do processo (Aguarda Cliente/Peças/Oficina/Terceiros)'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'process.change_status');

-- 3) Quem trata processos precisa dela: Admin, Supervisor e Operador.
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'process.change_status'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_OPERATOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
