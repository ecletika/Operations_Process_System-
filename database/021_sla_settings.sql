-- As duas regras do SLA justo passam a ser ligáveis/desligáveis em
-- Configurações → Parâmetros Globais, sem precisar de alterar código.
--
--   sla_renew_on_interaction = 1  → cada interação renova o prazo do SLA
--   sla_pause_on_waiting     = 1  → o SLA pára nos estados "Aguarda ..."
--
-- Desligar as duas devolve o comportamento original (prazo fixo desde a
-- criação do processo). Idempotente.
SET NAMES utf8mb4;

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'sla_renew_on_interaction', '1',
       'SLA: cada interação renova o prazo (1=sim, 0=não). Com 0, o prazo conta sempre desde a criação do processo.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'sla_renew_on_interaction');

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'sla_pause_on_waiting', '1',
       'SLA: fica em pausa enquanto o processo aguarda Cliente/Peças/Oficina/Terceiros (1=sim, 0=não).'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'sla_pause_on_waiting');
