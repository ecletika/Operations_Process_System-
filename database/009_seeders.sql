-- OPS-SQL-001 · 009_seeders.sql
-- Dados básicos para o sistema arrancar (OPS-SQL-001 secção 12).
-- O utilizador administrador é criado à parte por database/seed_admin.php
-- (precisa de password_hash() em PHP, não deve viver como hash fixo em SQL).

USE ops;

-- O cliente `mysql` no Windows costuma assumir o code page da consola
-- (ex.: CP850) em vez de UTF-8 ao ler este ficheiro via stdin, corrompendo
-- os acentos ao inserir. SET NAMES força a sessão a tratar os literais
-- deste ficheiro como utf8mb4, independentemente do codepage do terminal.
SET NAMES utf8mb4;

-- Perfis --------------------------------------------------------------------
INSERT INTO tb_role (uuid, code, name) VALUES
  (UUID(), 'ROLE_ADMIN', 'Administrador'),
  (UUID(), 'ROLE_SUPERVISOR', 'Supervisor'),
  (UUID(), 'ROLE_OPERATOR', 'Operador'),
  (UUID(), 'ROLE_VIEWER', 'Consulta');

-- Permissões (OPS-PRD-001 3.5 Matriz de Permissões) --------------------------
INSERT INTO tb_permission (uuid, code, description) VALUES
  (UUID(), 'dashboard.view', 'Visualizar dashboard'),
  (UUID(), 'process.create', 'Criar processo'),
  (UUID(), 'process.assume', 'Assumir processo'),
  (UUID(), 'process.edit', 'Editar processo'),
  (UUID(), 'process.edit_own', 'Editar apenas os seus processos'),
  (UUID(), 'process.close', 'Concluir processo'),
  (UUID(), 'process.reopen', 'Reabrir processo'),
  (UUID(), 'process.view_all', 'Ver todos os processos (não só a fila/os seus)'),
  (UUID(), 'process.delete', 'Excluir processo (apenas Administrador)'),
  (UUID(), 'records.delete', 'Excluir registos (clientes, viaturas)'),
  (UUID(), 'users.manage', 'Gerir utilizadores'),
  (UUID(), 'companies.manage', 'Gerir empresas'),
  (UUID(), 'branches.manage', 'Gerir filiais'),
  (UUID(), 'batches.manage', 'Gerir lotes'),
  (UUID(), 'subjects.manage', 'Gerir assuntos'),
  (UUID(), 'audit.view', 'Visualizar auditoria'),
  (UUID(), 'logs.view', 'Visualizar logs'),
  (UUID(), 'settings.manage', 'Gerir configurações'),
  (UUID(), 'reports.export', 'Exportar relatórios');

-- Associação Perfil <-> Permissão --------------------------------------------
-- ROLE_ADMIN: todas as permissões
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_ADMIN';

-- ROLE_SUPERVISOR
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_SUPERVISOR'
  AND p.code IN ('dashboard.view','process.create','process.assume','process.edit',
                 'process.close','process.reopen','process.view_all','reports.export');

-- ROLE_OPERATOR
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_OPERATOR'
  AND p.code IN ('dashboard.view','process.create','process.assume','process.edit_own','process.close');

-- ROLE_VIEWER
INSERT INTO tb_role_permission (uuid, role_id, permission_id)
SELECT UUID(), r.id, p.id
FROM tb_role r, tb_permission p
WHERE r.code = 'ROLE_VIEWER'
  AND p.code IN ('dashboard.view');

-- Estados (OPS-PRD-001 4.5) --------------------------------------------------
INSERT INTO tb_status (uuid, code, name, sort_order) VALUES
  (UUID(), 'NEW', 'Novo', 1),
  (UUID(), 'QUEUE', 'Em Fila', 2),
  (UUID(), 'ASSIGNED', 'Assumido', 3),
  (UUID(), 'IN_PROGRESS', 'Em Tratamento', 4),
  (UUID(), 'WAIT_CLIENT', 'Aguarda Cliente', 5),
  (UUID(), 'WAIT_PARTS', 'Aguarda Peças', 6),
  (UUID(), 'WAIT_WORKSHOP', 'Aguarda Oficina', 7),
  (UUID(), 'WAIT_EXTERNAL', 'Aguarda Terceiros', 8),
  (UUID(), 'SOLVED', 'Resolvido', 9),
  (UUID(), 'CLOSED', 'Encerrado', 10),
  (UUID(), 'REOPENED', 'Reaberto', 11);

-- Prioridades (OPS-PRD-001 4.13) ---------------------------------------------
INSERT INTO tb_priority (uuid, code, name, color, sort_order, default_sla_minutes) VALUES
  (UUID(), 'P1', 'Crítica', '#dc2626', 1, 60),
  (UUID(), 'P2', 'Alta', '#f97316', 2, 240),
  (UUID(), 'P3', 'Média', '#eab308', 3, 1440),
  (UUID(), 'P4', 'Baixa', '#22c55e', 4, 4320);

-- Assuntos (OPS-PRD-001 2.11 exemplo) ----------------------------------------
INSERT INTO tb_subject (uuid, code, name) VALUES
  (UUID(), 'ASSISTANCE', 'Assistência'),
  (UUID(), 'CLAIM', 'Sinistro'),
  (UUID(), 'TOW', 'Reboque'),
  (UUID(), 'WORKSHOP', 'Oficina'),
  (UUID(), 'INFO', 'Informação');

-- Configurações globais (OPS-PRD-001 4.7, 10.20) ------------------------------
INSERT INTO tb_setting (uuid, `key`, `value`, description) VALUES
  (UUID(), 'reopen_window_days', '30', 'Janela de reincidência em dias'),
  (UUID(), 'session_timeout_minutes', '60', 'Tempo de sessão'),
  (UUID(), 'dashboard_refresh_seconds', '30', 'Intervalo de atualização do dashboard'),
  (UUID(), 'login_max_attempts', '5', 'Tentativas de login antes de bloquear'),
  (UUID(), 'login_lock_minutes', '15', 'Duração do bloqueio após tentativas inválidas'),
  (UUID(), 'forgotten_process_hours', '48', 'RN-0056: horas sem atualização até o processo ser considerado esquecido'),
  (UUID(), 'operator_overload_limit', '30', 'RN-0057: nº máximo de processos ativos por operador'),
  (UUID(), 'frequent_customer_threshold', '5', 'RN-0059: nº de processos no período para marcar Cliente Frequente'),
  (UUID(), 'recurrent_vehicle_threshold', '3', 'RN-0060: nº de processos no período para marcar Viatura Recorrente'),
  (UUID(), 'recurrence_window_days', '90', 'Janela em dias usada para calcular Cliente Frequente / Viatura Recorrente'),
  (UUID(), 'archive_concluded_after_days', '30', 'Dias após conclusão até o processo ser arquivado automaticamente'),
  (UUID(), 'delete_archived_after_days', '180', 'Dias após arquivamento até o processo ser excluído automaticamente (vai para a Lixeira)');

-- Estrutura organizacional mínima --------------------------------------------
INSERT INTO tb_company (uuid, code, name) VALUES (UUID(), 'OPS', 'Operations Process System');
INSERT INTO tb_branch (uuid, company_id, code, name)
  SELECT UUID(), id, 'LIS', 'Lisboa' FROM tb_company WHERE code = 'OPS';
INSERT INTO tb_department (uuid, branch_id, code, name)
  SELECT UUID(), id, 'WORKSHOP', 'Oficina' FROM tb_branch WHERE code = 'LIS';
INSERT INTO tb_batch (uuid, department_id, code, description)
  SELECT UUID(), id, 'IL-132', 'Lote inicial' FROM tb_department WHERE code = 'WORKSHOP';
