-- 1) Próximo Contacto com o Cliente passa a ser configurado POR PRIORIDADE
--    (Configurações → Prioridades). Serve o caso real: com o SLA em pausa
--    (processo em espera) o cliente não pode ficar esquecido — volta-se a
--    contactá-lo de X em X horas, e o X depende da prioridade.
--      • preenchido (> 0) → ao pôr em espera, o sistema agenda sozinho o
--        próximo contacto (+X h); cada contacto registado empurra o seguinte;
--        ao retomar o tratamento o lembrete é removido. O calendário na ficha
--        do processo fica inibido (não se escolhe à mão o que é automático);
--      • vazio ou 0      → sem lembrete; o calendário fica ativo para o
--        operador escolher a data.
--
-- 2) Botão "Voltar para a Fila" passa a poder ser escondido em Configurações,
--    sem perder a funcionalidade.
--
-- Idempotente.
SET NAMES utf8mb4;

-- 1) Coluna nova na Prioridade.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_priority'
             AND column_name = 'next_contact_auto_hours');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_priority ADD COLUMN next_contact_auto_hours INT NULL AFTER default_sla_minutes',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Ponto de partida: a prioridade que hoje usa a definição global (por
--    omissão P4/Baixa) fica com esse mesmo número de horas, para não começar
--    do zero. As restantes ficam vazias — o Administrador preenche em
--    Configurações → Prioridades as que quiser, uma a uma.
UPDATE tb_priority p
  JOIN tb_setting s_code  ON s_code.`key`  = 'next_contact_priority_code'
  JOIN tb_setting s_hours ON s_hours.`key` = 'next_contact_auto_hours'
   SET p.next_contact_auto_hours = CAST(s_hours.`value` AS UNSIGNED)
 WHERE p.code = TRIM(s_code.`value`)
   AND p.deleted_at IS NULL
   AND p.next_contact_auto_hours IS NULL
   AND CAST(s_hours.`value` AS UNSIGNED) > 0;

-- 3) A definição global deixa de ser lida pelo código — fica só como memória
--    do valor anterior. A descrição diz onde é que se configura agora.
--    (next_contact_priority_code / next_contact_subject_code continuam a ser
--    usados: são eles que decidem em que processos aparece o calendário.)
UPDATE tb_setting
   SET description = 'SUBSTITUÍDO: já não é usado. O contacto periódico com o cliente configura-se agora por Prioridade, em Configurações → Prioridades (coluna "Próx. Contacto Cliente"), e só corre enquanto o SLA está em pausa. Este valor foi copiado para a prioridade correspondente.'
 WHERE `key` = 'next_contact_auto_hours';

-- 4) Interruptor do botão "Voltar para a Fila".
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'show_release_button', '1',
       'Mostrar o botão "Voltar para a Fila" na ficha do processo (1=sim, 0=esconder). Esconder só tira o botão da tela; a funcionalidade continua a existir e pode ser reativada aqui a qualquer momento.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'show_release_button');
