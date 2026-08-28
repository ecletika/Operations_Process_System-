-- Tempo em que um processo esteve ENCERRADO, entre um fecho e uma reabertura.
--
-- PORQUÊ: o SLA contava do início até ao fecho final, e isso incluía os dias
-- em que o processo esteve resolvido, à espera de ser reaberto. O caso real
-- PR-2026-00001155: aberto dia 24 às 15:38 e resolvido às 16:28 (50 min),
-- reaberto dia 26 às 11:18 e encerrado às 11:46 (28 min) — 1h18m de trabalho
-- que apareciam como 13h37m. O tempo em que esteve fechado não é de ninguém.
--
-- COMO: soma-se em tb_process.sla_closed_minutes o tempo (em minutos de
-- atendimento) de cada período fechado→reaberto, reconstruído dos eventos
-- PROCESS_CLOSED / PROCESS_REOPENED da Timeline, que são imutáveis (RN-0026).
-- A contagem do SLA passa a descontar esse valor.
--
-- Corrige ainda um efeito lateral: ao reabrir, o closed_at antigo não era
-- limpo, pelo que um processo reaberto e ainda EM CURSO continuava a ser
-- contado como concluído nos relatórios. Aqui limpa-se o closed_at desses
-- processos (só os que não estão num estado concluído).
--
-- DEPENDE de 034_recalcular_sla_pausas.sql, que instala fn_ops_minutos_uteis.
--
-- SEGURANÇA:
--   · Idempotente: recalcula sempre do zero a partir dos eventos.
--   · Processos nunca reabertos ficam a 0 e não são tocados.
--   · Deixa registo em tb_sla_closed_recalc_log (antes → depois, por processo).
--
-- Correr no phpMyAdmin (aba SQL), a base de dados do OPS selecionada.
SET NAMES utf8mb4;

-- 1) Coluna nova (só se ainda não existir).
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process'
             AND column_name = 'sla_closed_minutes');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN sla_closed_minutes INT NOT NULL DEFAULT 0 AFTER sla_paused_minutes',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Registo de auditoria.
CREATE TABLE IF NOT EXISTS tb_sla_closed_recalc_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    process_id   BIGINT UNSIGNED NOT NULL,
    minutos_antes  INT NOT NULL,
    minutos_depois INT NOT NULL,
    KEY idx_closed_recalc_process (process_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS sp_ops_recalcular_tempo_encerrado;

DELIMITER $$

-- ---------------------------------------------------------------------------
-- Percorre os eventos de fecho/reabertura por processo e regrava
-- sla_closed_minutes. Mesma mecânica do recálculo das pausas (034), mas com
-- o par PROCESS_CLOSED → PROCESS_REOPENED.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_ops_recalcular_tempo_encerrado()
BEGIN
    DECLARE v_fim_cursor BOOLEAN DEFAULT FALSE;
    DECLARE v_process_id BIGINT UNSIGNED;
    DECLARE v_tipo VARCHAR(60);
    DECLARE v_quando DATETIME;

    DECLARE v_atual BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_soma INT DEFAULT 0;
    DECLARE v_fechado_em DATETIME DEFAULT NULL;

    DECLARE cur CURSOR FOR
        SELECT e.process_id, e.event_type, e.created_at
          FROM tb_event e
          JOIN tb_process p ON p.id = e.process_id AND p.deleted_at IS NULL
         WHERE e.event_type IN ('PROCESS_CLOSED', 'PROCESS_REOPENED')
           AND e.deleted_at IS NULL
         ORDER BY e.process_id ASC, e.created_at ASC, e.id ASC;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fim_cursor = TRUE;

    OPEN cur;

    leitura: LOOP
        FETCH cur INTO v_process_id, v_tipo, v_quando;

        IF v_fim_cursor OR (v_atual IS NOT NULL AND v_process_id <> v_atual) THEN
            INSERT INTO tb_sla_closed_recalc_log (process_id, minutos_antes, minutos_depois)
            SELECT v_atual, sla_closed_minutes, v_soma
              FROM tb_process WHERE id = v_atual AND sla_closed_minutes <> v_soma;

            UPDATE tb_process SET sla_closed_minutes = v_soma
             WHERE id = v_atual AND sla_closed_minutes <> v_soma;

            SET v_atual = NULL;
            SET v_soma = 0;
            SET v_fechado_em = NULL;
        END IF;

        IF v_fim_cursor THEN
            LEAVE leitura;
        END IF;

        SET v_atual = v_process_id;

        IF v_tipo = 'PROCESS_CLOSED' THEN
            -- Fechos seguidos sem reabertura pelo meio: vale o primeiro.
            IF v_fechado_em IS NULL THEN
                SET v_fechado_em = v_quando;
            END IF;
        ELSE
            -- Reabertura sem fecho antes é ignorada. O último fecho, sem
            -- reabertura a seguir, também não conta: o processo está mesmo
            -- encerrado, não é tempo "parado".
            IF v_fechado_em IS NOT NULL THEN
                SET v_soma = v_soma + fn_ops_minutos_uteis(v_fechado_em, v_quando);
                SET v_fechado_em = NULL;
            END IF;
        END IF;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

-- 3) Execução: só com o horário de atendimento LIGADO (é a regra que dá
--    sentido aos minutos úteis).
SET @ligado := (SELECT `value` FROM tb_setting WHERE `key` = 'sla_business_hours_enabled' LIMIT 1);
SET @antes_desta_execucao := (SELECT IFNULL(MAX(id), 0) FROM tb_sla_closed_recalc_log);

SET @sql := IF(@ligado = '1', 'CALL sp_ops_recalcular_tempo_encerrado()', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Processos REABERTOS que ficaram com o closed_at do fecho anterior: sem
--    isto continuam a entrar nos relatórios como concluídos.
UPDATE tb_process p
  JOIN tb_status s ON s.id = p.status_id
   SET p.closed_at = NULL, p.closed_by = NULL
 WHERE p.deleted_at IS NULL
   AND p.closed_at IS NOT NULL
   AND s.code NOT IN ('SOLVED', 'CLOSED');

SELECT ROW_COUNT() AS reabertos_com_closed_at_limpo;

-- 5) Resumo desta execução.
SELECT
    IF(@ligado = '1', 'Recálculo executado', 'IGNORADO: o horário de atendimento está desligado') AS estado,
    (SELECT COUNT(*) FROM tb_sla_closed_recalc_log WHERE id > @antes_desta_execucao) AS processos_corrigidos,
    (SELECT IFNULL(SUM(minutos_depois), 0) FROM tb_sla_closed_recalc_log
      WHERE id > @antes_desta_execucao) AS minutos_encerrado_registados;

-- 6) Detalhe: que processos mudaram e quanto tempo estiveram encerrados.
SELECT p.process_number AS processo,
       p.reopen_count AS reaberturas,
       l.minutos_antes,
       l.minutos_depois AS minutos_encerrado
  FROM tb_sla_closed_recalc_log l
  JOIN tb_process p ON p.id = l.process_id
 WHERE l.id > @antes_desta_execucao
 ORDER BY l.minutos_depois DESC;

DROP PROCEDURE IF EXISTS sp_ops_recalcular_tempo_encerrado;
