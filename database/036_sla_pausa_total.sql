-- O tempo em PAUSA passa a parar mesmo o relógio do SLA.
--
-- PORQUÊ: quando um processo é posto em espera, a Timeline escreve "Em espera
-- … (SLA em pausa)" e, ao retomar, "SLA a contar". Só que o Tempo Total do
-- processo — e o Relatório SLA — continuavam a somar esse período. O caso real
-- PR-2026-00001287: aberto às 14:19, posto em espera às 14:32 porque o cliente
-- não atendia, retomado às 16:19 e concluído às 16:24. São ~18 minutos de
-- trabalho, mas apareciam 2h04m, com 1h47m de espera lá dentro. Penalizava o
-- operador por tempo que não dependia dele.
--
-- COMO: além de sla_paused_minutes, que já existia, passa a haver
-- sla_paused_total_minutes. São dois porque servem coisas diferentes:
--
--   sla_paused_minutes        é ZERADO a cada contacto (quando o SLA renova na
--                             interação). Vale "pausa desde o último contacto"
--                             e serve o relógio ao vivo da fila.
--   sla_paused_total_minutes  nunca é zerado. É o total do processo, e é o que
--                             se desconta ao tempo de SLA.
--
-- Esta migration cria a coluna nova e preenche-a com o total real de cada
-- processo, reconstruído dos eventos PROCESS_WAITING / PROCESS_RESUMED da
-- Timeline, que são imutáveis (RN-0026).
--
-- DEPENDE de 034_recalcular_sla_pausas.sql, que instala fn_ops_minutos_uteis.
--
-- SEGURANÇA:
--   · Idempotente: recalcula sempre do zero a partir dos eventos.
--   · Uma espera ainda a decorrer não entra — o relógio ao vivo trata dela.
--   · Processos nunca pausados ficam a 0 e não são tocados.
--   · Deixa registo em tb_sla_pause_total_log (antes → depois, por processo).
--
-- Correr no phpMyAdmin (aba SQL), a base de dados do OPS selecionada.
SET NAMES utf8mb4;

-- 1) Coluna nova (só se ainda não existir).
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process'
             AND column_name = 'sla_paused_total_minutes');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN sla_paused_total_minutes INT NOT NULL DEFAULT 0 AFTER sla_paused_minutes',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Registo de auditoria.
CREATE TABLE IF NOT EXISTS tb_sla_pause_total_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    process_id   BIGINT UNSIGNED NOT NULL,
    minutos_antes  INT NOT NULL,
    minutos_depois INT NOT NULL,
    KEY idx_pause_total_process (process_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS sp_ops_recalcular_pausa_total;

DELIMITER $$

-- ---------------------------------------------------------------------------
-- Soma, por processo, TODAS as esperas já terminadas — a mesma mecânica do
-- recálculo das pausas (034), mas a escrever no contador que nunca é zerado.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_ops_recalcular_pausa_total()
BEGIN
    DECLARE v_fim_cursor BOOLEAN DEFAULT FALSE;
    DECLARE v_process_id BIGINT UNSIGNED;
    DECLARE v_tipo VARCHAR(60);
    DECLARE v_quando DATETIME;

    DECLARE v_atual BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_soma INT DEFAULT 0;
    DECLARE v_inicio DATETIME DEFAULT NULL;

    DECLARE cur CURSOR FOR
        SELECT e.process_id, e.event_type, e.created_at
          FROM tb_event e
          JOIN tb_process p ON p.id = e.process_id AND p.deleted_at IS NULL
         WHERE e.event_type IN ('PROCESS_WAITING', 'PROCESS_RESUMED')
           AND e.deleted_at IS NULL
         ORDER BY e.process_id ASC, e.created_at ASC, e.id ASC;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fim_cursor = TRUE;

    OPEN cur;

    leitura: LOOP
        FETCH cur INTO v_process_id, v_tipo, v_quando;

        IF v_fim_cursor OR (v_atual IS NOT NULL AND v_process_id <> v_atual) THEN
            INSERT INTO tb_sla_pause_total_log (process_id, minutos_antes, minutos_depois)
            SELECT v_atual, sla_paused_total_minutes, v_soma
              FROM tb_process WHERE id = v_atual AND sla_paused_total_minutes <> v_soma;

            UPDATE tb_process SET sla_paused_total_minutes = v_soma
             WHERE id = v_atual AND sla_paused_total_minutes <> v_soma;

            SET v_atual = NULL;
            SET v_soma = 0;
            SET v_inicio = NULL;
        END IF;

        IF v_fim_cursor THEN
            LEAVE leitura;
        END IF;

        SET v_atual = v_process_id;

        IF v_tipo = 'PROCESS_WAITING' THEN
            -- Waiting repetido sem resumed pelo meio: conta desde o primeiro.
            IF v_inicio IS NULL THEN
                SET v_inicio = v_quando;
            END IF;
        ELSE
            -- Resumed sem waiting antes é ignorado.
            IF v_inicio IS NOT NULL THEN
                SET v_soma = v_soma + fn_ops_minutos_uteis(v_inicio, v_quando);
                SET v_inicio = NULL;
            END IF;
        END IF;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

-- 3) Execução: só com o horário de atendimento LIGADO.
SET @ligado := (SELECT `value` FROM tb_setting WHERE `key` = 'sla_business_hours_enabled' LIMIT 1);
SET @antes_desta_execucao := (SELECT IFNULL(MAX(id), 0) FROM tb_sla_pause_total_log);

SET @sql := IF(@ligado = '1', 'CALL sp_ops_recalcular_pausa_total()', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Resumo desta execução.
SELECT
    IF(@ligado = '1', 'Recálculo executado', 'IGNORADO: o horário de atendimento está desligado') AS estado,
    (SELECT COUNT(*) FROM tb_sla_pause_total_log WHERE id > @antes_desta_execucao) AS processos_com_pausa,
    (SELECT IFNULL(SUM(minutos_depois), 0) FROM tb_sla_pause_total_log
      WHERE id > @antes_desta_execucao) AS minutos_pausa_descontados;

-- 5) Detalhe: quanto tempo de pausa deixa de contar em cada processo.
SELECT p.process_number AS processo,
       l.minutos_depois AS pausa_minutos,
       fn_ops_minutos_uteis(p.created_at, p.closed_at) AS tempo_antes,
       fn_ops_minutos_uteis(p.created_at, p.closed_at)
         - l.minutos_depois - p.sla_closed_minutes AS tempo_agora
  FROM tb_sla_pause_total_log l
  JOIN tb_process p ON p.id = l.process_id
 WHERE l.id > @antes_desta_execucao AND p.closed_at IS NOT NULL
 ORDER BY l.minutos_depois DESC
 LIMIT 50;

DROP PROCEDURE IF EXISTS sp_ops_recalcular_pausa_total;
