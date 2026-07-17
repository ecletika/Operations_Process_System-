-- Nova Data de Contacto (agendar novo contacto com o cliente).
--
-- Aparece apenas quando o processo tem, EM SIMULTÂNEO, a prioridade e o
-- assunto definidos abaixo — hoje "Baixa" (P4) + "Imobilizados" (IMO).
-- A combinação fica em Configurações, para poder ser mudada sem código.
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) A data agendada no processo.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process' AND column_name = 'next_contact_at');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN next_contact_at DATE NULL AFTER last_contact_at',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Que combinação ativa o botão (códigos de tb_priority / tb_subject).
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'next_contact_priority_code', 'P4',
       'Nova Data de Contacto: código da prioridade que ativa o botão (ex.: P4 = Baixa). Vazio = qualquer prioridade.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'next_contact_priority_code');

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'next_contact_subject_code', 'IMO',
       'Nova Data de Contacto: código do assunto que ativa o botão (ex.: IMO = Imobilizados). Vazio = qualquer assunto.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'next_contact_subject_code');

-- 3) Permissão dedicada e atribuível.
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.next_contact', 'Definir a Nova Data de Contacto no processo'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'process.next_contact');

-- 4) Quem trata processos precisa dela.
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'process.next_contact'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_OPERATOR', 'ROLE_DEPT_SUPERVISOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
