-- Parâmetro por utilizador: visibilidade da Fila Inteligente™.
-- 0 = vê apenas os processos do seu próprio lote (comportamento normal do Operador)
-- 1 = vê os processos de todos os lotes (todas as filiais)
SET NAMES utf8mb4;

ALTER TABLE tb_user
    ADD COLUMN view_all_batches TINYINT(1) NOT NULL DEFAULT 0 AFTER default_batch_id;
