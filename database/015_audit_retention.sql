-- Retenção da auditoria: os registos de auditoria com mais de N dias são
-- apagados automaticamente pelo cron (política de retenção pedida pelo cliente).
SET NAMES utf8mb4;

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'audit_retention_days', '60', 'Dias de retenção da auditoria; registos mais antigos são apagados automaticamente'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'audit_retention_days');
