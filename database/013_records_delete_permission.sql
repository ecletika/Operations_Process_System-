-- Nova permissão: excluir registos (clientes, viaturas). Atribuível na matriz
-- de permissões (ACL). O Administrador recebe-a automaticamente.
SET NAMES utf8mb4;

INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'records.delete', 'Excluir registos (clientes, viaturas)'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM tb_permission WHERE code = 'records.delete');

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'records.delete'
WHERE r.code = 'ROLE_ADMIN'
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
