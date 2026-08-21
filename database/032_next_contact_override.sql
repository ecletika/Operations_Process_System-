-- Editar MANUALMENTE a Nova Data de Contacto mesmo nos Imobilizados (onde é
-- calculada automaticamente). A contagem automática continua a funcionar; os
-- perfis de gestão/supervisão podem apenas sobrepor a data à mão quando
-- precisarem.
--
-- Permissão dedicada e atribuível: Admin, Supervisor e Supervisor de
-- Departamento. Os Operadores continuam a ver a data bloqueada (automática).
--
-- Idempotente.
SET NAMES utf8mb4;

INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.next_contact_override', 'Editar manualmente a Nova Data de Contacto mesmo quando é automática'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'process.next_contact_override');

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'process.next_contact_override'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_DEPT_SUPERVISOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
