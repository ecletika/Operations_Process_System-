-- "Assuntos por Departamento" passa a ter a sua própria permissão dedicada
-- (subjects.manage), atribuível no ecrã Perfis & Permissões — assim a função
-- pode ser dada só a quem deve, sem depender de settings.manage.
--
-- Idempotente: pode correr-se várias vezes sem problema.
SET NAMES utf8mb4;

-- 1) Garante que a permissão existe (em bases de dados antigas).
INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'subjects.manage', 'Configurar Assuntos por Departamento (Novo Processo)'
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'subjects.manage');

-- 2) Descrição clara na matriz de Perfis & Permissões.
UPDATE tb_permission
SET description = 'Configurar Assuntos por Departamento (Novo Processo)'
WHERE code = 'subjects.manage';

-- 3) O Administrador tem sempre esta permissão.
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'subjects.manage'
WHERE r.code = 'ROLE_ADMIN'
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
