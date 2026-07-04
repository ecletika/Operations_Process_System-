-- Ciclo de vida de arquivamento dos processos:
--  1) processos concluídos há mais de N dias são ARQUIVADOS (saem das listas ativas)
--  2) processos arquivados há mais de 180 dias são EXCLUÍDOS automaticamente
--     (soft-delete → vão para a Lixeira, ainda recuperáveis)
SET NAMES utf8mb4;

-- Marca quando o processo foi arquivado (para contar os 180 dias).
ALTER TABLE tb_process
    ADD COLUMN archived_at DATETIME NULL AFTER archived;

-- Parâmetros configuráveis (Configurações).
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'archive_concluded_after_days', '30', 'Dias após conclusão até o processo ser arquivado automaticamente'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'archive_concluded_after_days');

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'delete_archived_after_days', '180', 'Dias após arquivamento até o processo ser excluído automaticamente (vai para a Lixeira)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'delete_archived_after_days');
