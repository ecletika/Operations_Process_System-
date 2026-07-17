-- Transferir um processo para outra Filial/Departamento (ex.: foi criado na
-- filial errada e a equipa certa não lhe consegue tocar). O processo volta à
-- fila do departamento de destino, sem responsável.
--
-- Permissão dedicada e atribuível: Admin, Supervisor e Supervisor de
-- Departamento (o Supervisor de Departamento só transfere processos do SEU
-- departamento — a guarda está no código).
--
-- Idempotente.
SET NAMES utf8mb4;

INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.transfer', 'Transferir o processo para outra Filial/Departamento'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'process.transfer');

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'process.transfer'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_DEPT_SUPERVISOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
