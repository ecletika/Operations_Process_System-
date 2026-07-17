-- Departamento de ORIGEM do processo — para onde foi criado. Ao contrário do
-- batch_id (que muda quando o processo é transferido), este fica fixo, para
-- se poder sempre rastrear onde o processo nasceu. Resolve o "gap" de não se
-- conseguir saber para que departamento um processo foi criado.
--
-- Idempotente.
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process' AND column_name = 'origin_batch_id');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN origin_batch_id BIGINT UNSIGNED NULL AFTER batch_id',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Processos que já existem: assume-se que a origem é o lote atual (o melhor
-- que se sabe hoje; os futuros ficam com a origem exata na criação).
UPDATE tb_process SET origin_batch_id = batch_id WHERE origin_batch_id IS NULL;
