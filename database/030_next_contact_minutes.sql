-- O contacto periódico com o cliente passa a ser medido em MINUTOS, como o
-- SLA (tb_priority.default_sla_minutes), e a contagem passa a respeitar o
-- horário de atendimento: com sla_business_hours_enabled = 1 os minutos só
-- correm dentro do horário e saltam fins de semana e feriados, para nunca
-- marcar um contacto para domingo de madrugada.
--
-- Idempotente e independente da 029: funciona quer essa já tenha sido
-- aplicada (renomeia e converte), quer não (cria a coluna já em minutos).
SET NAMES utf8mb4;

SET @tem_min := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'tb_priority'
                   AND column_name = 'next_contact_auto_minutes');
SET @tem_hor := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'tb_priority'
                   AND column_name = 'next_contact_auto_hours');

-- a) A 029 já tinha criado a coluna em horas: renomeia para minutos.
SET @s := IF(@tem_min = 0 AND @tem_hor = 1,
  'ALTER TABLE tb_priority CHANGE COLUMN next_contact_auto_hours next_contact_auto_minutes INT NULL',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- b) ...e converte os valores que lá estavam (horas → minutos).
SET @s := IF(@tem_min = 0 AND @tem_hor = 1,
  'UPDATE tb_priority SET next_contact_auto_minutes = next_contact_auto_minutes * 60
    WHERE next_contact_auto_minutes IS NOT NULL',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- c) A 029 nunca foi aplicada: cria a coluna já na unidade final.
SET @s := IF(@tem_min = 0 AND @tem_hor = 0,
  'ALTER TABLE tb_priority ADD COLUMN next_contact_auto_minutes INT NULL AFTER default_sla_minutes',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- d) Só nesse caso (coluna acabada de nascer), dá um ponto de partida à
--    prioridade que usava a definição global antiga, já em minutos. Assim
--    correr esta migração de novo nunca ressuscita um valor que o
--    Administrador tenha limpado de propósito.
SET @s := IF(@tem_min = 0 AND @tem_hor = 0,
  'UPDATE tb_priority p
     JOIN tb_setting s_code  ON s_code.`key`  = ''next_contact_priority_code''
     JOIN tb_setting s_hours ON s_hours.`key` = ''next_contact_auto_hours''
      SET p.next_contact_auto_minutes = CAST(s_hours.`value` AS UNSIGNED) * 60
    WHERE p.code = TRIM(s_code.`value`)
      AND p.deleted_at IS NULL
      AND p.next_contact_auto_minutes IS NULL
      AND CAST(s_hours.`value` AS UNSIGNED) > 0',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- e) Descrição da definição global antiga, agora que a unidade mudou.
UPDATE tb_setting
   SET description = 'SUBSTITUÍDO: já não é usado. O contacto periódico com o cliente configura-se por Prioridade, em Configurações → Prioridades (coluna "Próx. Contacto Cliente (min)"), é contado em minutos de atendimento e só corre enquanto o SLA está em pausa.'
 WHERE `key` = 'next_contact_auto_hours';
