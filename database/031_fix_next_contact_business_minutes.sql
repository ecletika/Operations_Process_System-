-- Corrige o valor que a migração 029/030 copiou para a Prioridade que usava
-- a antiga definição global de "Nova Data de Contacto" (por omissão, Baixa).
--
-- Essa cópia foi uma conversão direta de horas corridas para minutos
-- (48h -> 2880 min), mas o Próximo Contacto por Prioridade conta em MINUTOS
-- DE ATENDIMENTO (para nunca cair a um sábado/domingo) — nessa unidade,
-- 2880 minutos de expediente já não são "2 dias", são quase uma semana.
-- O valor certo para "2 dias" é 16h de atendimento = 960 minutos.
--
-- Só corrige quem ainda tem exatamente o valor herdado (2880): se um
-- Administrador já tiver mudado isto à mão em Configurações → Prioridades,
-- esta migração não lhe mexe.
--
-- Idempotente.
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_priority'
             AND column_name = 'next_contact_auto_minutes');

SET @s := IF(@c = 1,
  'UPDATE tb_priority SET next_contact_auto_minutes = 960 WHERE next_contact_auto_minutes = 2880',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
