-- Nova Data de Contacto passa a guardar DATA+HORA (não só o dia), para:
--   1) poder ser preenchida automaticamente 48h após um contacto/registo
--      num processo Imobilizados (IMO) + Baixa (P4);
--   2) o lembrete (pop-up de notificação) avisar à hora certa, não só no dia.
--
-- Datas já guardadas ficam à meia-noite (00:00) desse dia — nunca tinham
-- hora associada, por isso não há informação a perder.
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) DATE -> DATETIME.
SET @tipo := (SELECT DATA_TYPE FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'tb_process' AND column_name = 'next_contact_at');
SET @s := IF(@tipo = 'date',
  'ALTER TABLE tb_process MODIFY COLUMN next_contact_at DATETIME NULL',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Horas após o contacto/registo para o preenchimento automático (mesma
--    combinação de Prioridade/Assunto de next_contact_priority_code /
--    next_contact_subject_code). 0 = desativado.
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'next_contact_auto_hours', '48',
       'Nova Data de Contacto: horas (corridas) após um contacto/observação registado num processo com a combinação de Prioridade/Assunto definida acima, para preencher automaticamente a Nova Data de Contacto. 0 = desativado.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'next_contact_auto_hours');

-- 3) Janela do lembrete: quantos minutos antes/depois da hora marcada é que
--    o pop-up avisa, e de quanto em quanto tempo pode repetir o aviso.
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'next_contact_reminder_repeat_minutes', '60',
       'Lembrete de Próximo Contacto: de quantos em quantos minutos o aviso pode repetir-se enquanto o contacto não for feito (evita spam).'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'next_contact_reminder_repeat_minutes');

-- 4) Link opcional na notificação, para o pop-up (toast) poder levar direto
--    ao processo em vez de só à fila genérica.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_notification' AND column_name = 'link');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_notification ADD COLUMN link VARCHAR(255) NULL AFTER message',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
