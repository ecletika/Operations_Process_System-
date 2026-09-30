-- Permissão para o separador "Imobilizados (todos)" da Caixa de Entrada.
--
-- Esse separador mostra os imobilizados de TODA a gente, e não só os que a
-- pessoa assumiu ou criou. Nem todos os perfis devem vê-lo, por isso passa a
-- ter permissão própria, que o Administrador liga ou desliga por perfil em
-- Configurações → Permissões (o ecrã lista as permissões que existirem na
-- base de dados, portanto esta aparece lá sozinha).
--
-- O código começa por "process." de propósito: é assim que o ecrã de
-- permissões a arruma no grupo "Processos", junto das outras.
--
-- Fica ligada para Administrador e Supervisor, que são os perfis que já têm
-- visão para lá do seu próprio trabalho. Qualquer outro perfil tem de ser
-- ligado à mão — quem não a tiver nem vê o separador.
--
-- O separador é só de leitura: esta permissão dá acesso a VER, nunca a
-- alterar. Editar continua a depender das permissões de sempre e do
-- isolamento por departamento.
--
-- Idempotente. Correr no phpMyAdmin (aba SQL).
SET NAMES utf8mb4;

INSERT INTO tb_permission (uuid, code, description)
SELECT UUID(), 'process.view_all_imobilizados',
       'Ver o separador "Imobilizados (todos)" na Caixa de Entrada (apenas leitura)'
WHERE NOT EXISTS (
    SELECT 1 FROM tb_permission WHERE code = 'process.view_all_imobilizados'
);

INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r
JOIN tb_permission p ON p.code = 'process.view_all_imobilizados'
WHERE r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR')
  AND NOT EXISTS (
    SELECT 1 FROM tb_role_permission rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

SELECT r.name AS perfil,
       IF(rp.id IS NULL, 'não', 'sim') AS ve_imobilizados_todos
  FROM tb_role r
  LEFT JOIN tb_permission p ON p.code = 'process.view_all_imobilizados'
  LEFT JOIN tb_role_permission rp ON rp.role_id = r.id AND rp.permission_id = p.id
 WHERE r.deleted_at IS NULL
 ORDER BY r.name;
