-- Horário de almoço no horário de atendimento: o SLA (e a contagem de minutos
-- úteis) deixa de contar durante o almoço. Cada dia pode ter um intervalo de
-- almoço (início e fim); vazio = sem almoço nesse dia.
--
-- Idempotente. Correr no phpMyAdmin (aba SQL).
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_business_hours'
             AND column_name = 'lunch_start');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_business_hours
     ADD COLUMN lunch_start TIME NULL AFTER close_time,
     ADD COLUMN lunch_end   TIME NULL AFTER lunch_start',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
