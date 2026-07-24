-- Verificação rápida: confirma se as migrações recentes estão aplicadas.
-- Correr no phpMyAdmin (aba SQL) sobre a base de dados do OPS.
-- Só lê — não altera nada.

SELECT '017 · presença (last_activity_at)' AS verificacao,
       IF(COUNT(*) = 1, 'OK', 'FALTA') AS estado
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'tb_user' AND column_name = 'last_activity_at'

UNION ALL
SELECT '018 · assuntos por departamento',
       IF(COUNT(*) = 1, 'OK', 'FALTA')
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'tb_department_subject'

UNION ALL
SELECT '019 · permissão subjects.manage',
       IF(COUNT(*) > 0, 'OK', 'FALTA')
FROM tb_permission WHERE code = 'subjects.manage'

UNION ALL
SELECT '020 · campos de pausa do SLA no processo',
       IF(COUNT(*) = 2, 'OK', 'FALTA')
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'tb_process'
  AND column_name IN ('sla_paused_minutes', 'wait_started_at')

UNION ALL
SELECT '020 · permissão process.change_status',
       IF(COUNT(*) > 0, 'OK', 'FALTA')
FROM tb_permission WHERE code = 'process.change_status'

UNION ALL
SELECT '021 · interruptores do SLA (esperado 2)',
       IF(COUNT(*) = 2, 'OK', 'FALTA')
FROM tb_setting WHERE `key` IN ('sla_renew_on_interaction', 'sla_pause_on_waiting')

UNION ALL
SELECT '022 · coluna is_waiting nos estados',
       IF(COUNT(*) = 1, 'OK', 'FALTA')
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'tb_status' AND column_name = 'is_waiting'

UNION ALL
SELECT '022 · motivos de pausa marcados',
       CONCAT(COUNT(*), ' motivo(s)')
FROM tb_status WHERE is_waiting = 1 AND deleted_at IS NULL

UNION ALL
SELECT '022 · permissão sla_reasons.manage',
       IF(COUNT(*) > 0, 'OK', 'FALTA')
FROM tb_permission WHERE code = 'sla_reasons.manage'

UNION ALL
SELECT '029 · próximo contacto automático por prioridade',
       IF(COUNT(*) = 1, 'OK', 'FALTA')
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'tb_priority'
  AND column_name = 'next_contact_auto_hours'

UNION ALL
SELECT '029 · prioridades com agendamento automático',
       CONCAT(COUNT(*), ' prioridade(s)')
FROM tb_priority WHERE next_contact_auto_hours > 0 AND deleted_at IS NULL

UNION ALL
SELECT '029 · interruptor do botão Voltar para a Fila',
       IF(COUNT(*) = 1, 'OK', 'FALTA')
FROM tb_setting WHERE `key` = 'show_release_button';
