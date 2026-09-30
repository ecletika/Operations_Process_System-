-- Que departamentos podem ver o separador "Imobilizados (todos)".
--
-- A permissão por perfil (migração 039) diz QUE TIPO de utilizador pode ver
-- a lista; esta coluna diz de QUE DEPARTAMENTOS. São coisas diferentes: dois
-- operadores com o mesmo perfil podem estar em departamentos com necessidades
-- diferentes — a Oficina precisa de ver os carros parados, a Contabilidade
-- não tem nada a fazer com isso.
--
-- Fica em Configurações → Perfis & Permissões, no separador "Imobilizados",
-- com as duas listas lado a lado: os que têm acesso de um lado, os que não
-- têm do outro.
--
-- Por omissão NENHUM departamento fica marcado. É deliberado: quem decide
-- isto é a casa, e um acesso alargado por omissão é o tipo de coisa que
-- ninguém repara que está ligada.
--
-- Idempotente. Correr no phpMyAdmin (aba SQL).
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_department'
             AND column_name = 'imobilizados_view_all');

SET @s := IF(@c = 0,
  'ALTER TABLE tb_department
     ADD COLUMN imobilizados_view_all BOOLEAN NOT NULL DEFAULT 0 AFTER active',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SELECT CONCAT(br.name, ' · ', d.name) AS departamento,
       IF(d.imobilizados_view_all = 1, 'sim', 'não') AS ve_imobilizados_todos
  FROM tb_department d
  JOIN tb_branch br ON br.id = d.branch_id
 WHERE d.deleted_at IS NULL
 ORDER BY br.name, d.name;
