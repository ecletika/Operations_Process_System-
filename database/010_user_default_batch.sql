-- OPS-SQL-001 · 010_user_default_batch.sql
-- Um utilizador pode trabalhar em vários lotes (tb_user_batch, N:N),
-- mas precisa de um "Lote Padrão" para pré-preencher o Novo Processo
-- sem obrigar a escolher sempre que sessão é iniciada.

USE ops;

ALTER TABLE tb_user
  ADD COLUMN default_batch_id BIGINT UNSIGNED NULL AFTER department_id;

ALTER TABLE tb_user
  ADD CONSTRAINT fk_user_default_batch FOREIGN KEY (default_batch_id) REFERENCES tb_batch (id) ON UPDATE CASCADE ON DELETE RESTRICT;

CREATE INDEX idx_user_default_batch ON tb_user (default_batch_id);
