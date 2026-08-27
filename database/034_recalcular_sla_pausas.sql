-- Recalcula o tempo de PAUSA do SLA já gravado (tb_process.sla_paused_minutes).
--
-- PORQUÊ: até agora, ao sair de "em espera", a pausa era somada em tempo
-- corrido. Com o horário de atendimento ligado isso está errado — uma espera
-- das 17h às 9h do dia seguinte creditava 16 horas quando de expediente só
-- passaram 1h30m. Esse crédito a mais soma-se ao prazo e faz processos
-- parecerem cumpridos quando não foram. Como o SLA passou a decidir prémios,
-- os valores antigos têm de ser corrigidos.
--
-- COMO: as pausas são reconstruídas a partir dos eventos PROCESS_WAITING /
-- PROCESS_RESUMED da Timeline, que nunca são alterados nem apagados
-- (RN-0026), e voltam a ser somadas só com os minutos de atendimento.
--
-- SEGURANÇA:
--   · Só corre se "Contar o SLA apenas em horário de atendimento" estiver
--     LIGADO. Desligado, a contagem é 24h/dia e não há nada a corrigir.
--   · Processos com pausa gravada mas SEM eventos de espera na Timeline não
--     são tocados — não há como reconstruir, e não se inventa um número.
--   · Uma espera ainda a decorrer não entra: o relógio ao vivo trata dela.
--   · IDEMPOTENTE: recalcula sempre do zero a partir dos eventos, por isso
--     pode correr as vezes que forem precisas.
--   · Deixa registo em tb_sla_pause_recalc_log (antes → depois, por processo),
--     para se poder justificar qualquer alteração de prémio.
--
-- Correr no phpMyAdmin (aba SQL), a base de dados do OPS selecionada.
-- No fim, dois SELECT mostram o resumo e o detalhe do que mudou.
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Registo de auditoria do recálculo.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tb_sla_pause_recalc_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    process_id   BIGINT UNSIGNED NOT NULL,
    minutos_antes  INT NOT NULL,
    minutos_depois INT NOT NULL,
    KEY idx_recalc_process (process_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP FUNCTION IF EXISTS fn_ops_hora_local;
DROP FUNCTION IF EXISTS fn_ops_minutos_uteis;
DROP PROCEDURE IF EXISTS sp_ops_recalcular_pausas_sla;

DELIMITER $$

-- ---------------------------------------------------------------------------
-- UTC -> hora local de Portugal continental.
--
-- Não usa CONVERT_TZ porque as tabelas de fuso do MySQL costumam estar vazias
-- em alojamento partilhado. Aplica a regra da UE: +1h entre o último domingo
-- de março às 01:00 UTC e o último domingo de outubro às 01:00 UTC.
-- ---------------------------------------------------------------------------
CREATE FUNCTION fn_ops_hora_local(p_utc DATETIME)
RETURNS DATETIME
DETERMINISTIC
BEGIN
    DECLARE v_ano INT;
    DECLARE v_marco DATE;
    DECLARE v_outubro DATE;
    DECLARE v_inicio DATETIME;
    DECLARE v_fim DATETIME;

    IF p_utc IS NULL THEN
        RETURN NULL;
    END IF;

    SET v_ano = YEAR(p_utc);
    -- DAYOFWEEK: 1=Domingo. Recuar até ao domingo dá o ÚLTIMO domingo do mês
    -- (março e outubro têm 31 dias).
    SET v_marco = DATE(CONCAT(v_ano, '-03-31'));
    SET v_marco = DATE_SUB(v_marco, INTERVAL DAYOFWEEK(v_marco) - 1 DAY);
    SET v_outubro = DATE(CONCAT(v_ano, '-10-31'));
    SET v_outubro = DATE_SUB(v_outubro, INTERVAL DAYOFWEEK(v_outubro) - 1 DAY);

    SET v_inicio = TIMESTAMP(v_marco, '01:00:00');
    SET v_fim = TIMESTAMP(v_outubro, '01:00:00');

    IF p_utc >= v_inicio AND p_utc < v_fim THEN
        RETURN p_utc + INTERVAL 1 HOUR;
    END IF;

    RETURN p_utc;
END$$

-- ---------------------------------------------------------------------------
-- Minutos de ATENDIMENTO entre dois instantes (ambos em UTC, como estão
-- guardados). Mesma regra do BusinessClock em PHP: percorre dia a dia, salta
-- feriados e dias fechados, e desconta o almoço.
-- ---------------------------------------------------------------------------
CREATE FUNCTION fn_ops_minutos_uteis(p_de DATETIME, p_ate DATETIME)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_de DATETIME;
    DECLARE v_ate DATETIME;
    DECLARE v_dia DATE;
    DECLARE v_ultimo DATE;
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_guarda INT DEFAULT 0;
    DECLARE v_abre TIME;
    DECLARE v_fecha TIME;
    DECLARE v_almoco_ini TIME;
    DECLARE v_almoco_fim TIME;
    DECLARE v_tem_almoco BOOLEAN;
    DECLARE v_feriado INT;
    DECLARE v_ini DATETIME;
    DECLARE v_fim DATETIME;
    DECLARE v_seg_fim TIME;

    IF p_de IS NULL OR p_ate IS NULL THEN
        RETURN 0;
    END IF;

    SET v_de = fn_ops_hora_local(p_de);
    SET v_ate = fn_ops_hora_local(p_ate);

    IF v_ate <= v_de THEN
        RETURN 0;
    END IF;

    SET v_dia = DATE(v_de);
    SET v_ultimo = DATE(v_ate);

    WHILE v_dia <= v_ultimo AND v_guarda < 800 DO
        SET v_guarda = v_guarda + 1;
        SET v_abre = NULL;
        SET v_fecha = NULL;
        SET v_almoco_ini = NULL;
        SET v_almoco_fim = NULL;

        SELECT open_time, close_time, lunch_start, lunch_end
          INTO v_abre, v_fecha, v_almoco_ini, v_almoco_fim
          FROM tb_business_hours
         WHERE weekday = DAYOFWEEK(v_dia) - 1
         LIMIT 1;

        SET v_feriado = (
            SELECT COUNT(*) FROM tb_holiday
             WHERE active = 1 AND deleted_at IS NULL
               AND ((recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(v_dia, '%m-%d'))
                 OR (recurring = 0 AND holiday_date = v_dia))
        );

        IF v_feriado = 0 AND v_abre IS NOT NULL AND v_fecha IS NOT NULL AND v_fecha > v_abre THEN
            -- Almoço só conta se estiver bem definido e dentro da janela do dia.
            SET v_tem_almoco = (v_almoco_ini IS NOT NULL AND v_almoco_fim IS NOT NULL
                                AND v_almoco_fim > v_almoco_ini
                                AND v_almoco_ini >= v_abre AND v_almoco_fim <= v_fecha);

            -- Segmento 1: manhã (até ao almoço) ou o dia inteiro.
            SET v_seg_fim = IF(v_tem_almoco, v_almoco_ini, v_fecha);
            SET v_ini = GREATEST(v_de, TIMESTAMP(v_dia, v_abre));
            SET v_fim = LEAST(v_ate, TIMESTAMP(v_dia, v_seg_fim));
            IF v_fim > v_ini THEN
                SET v_total = v_total + TIMESTAMPDIFF(MINUTE, v_ini, v_fim);
            END IF;

            -- Segmento 2: tarde (só quando há almoço).
            IF v_tem_almoco THEN
                SET v_ini = GREATEST(v_de, TIMESTAMP(v_dia, v_almoco_fim));
                SET v_fim = LEAST(v_ate, TIMESTAMP(v_dia, v_fecha));
                IF v_fim > v_ini THEN
                    SET v_total = v_total + TIMESTAMPDIFF(MINUTE, v_ini, v_fim);
                END IF;
            END IF;
        END IF;

        SET v_dia = v_dia + INTERVAL 1 DAY;
    END WHILE;

    RETURN v_total;
END$$

-- ---------------------------------------------------------------------------
-- Percorre os eventos de espera por processo e regrava sla_paused_minutes.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_ops_recalcular_pausas_sla()
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

        -- Fecha o processo anterior quando muda de processo (ou no fim).
        IF v_fim_cursor OR (v_atual IS NOT NULL AND v_process_id <> v_atual) THEN
            INSERT INTO tb_sla_pause_recalc_log (process_id, minutos_antes, minutos_depois)
            SELECT v_atual, sla_paused_minutes, v_soma
              FROM tb_process WHERE id = v_atual AND sla_paused_minutes <> v_soma;

            UPDATE tb_process SET sla_paused_minutes = v_soma
             WHERE id = v_atual AND sla_paused_minutes <> v_soma;

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

-- ---------------------------------------------------------------------------
-- Execução: só com o horário de atendimento LIGADO.
-- ---------------------------------------------------------------------------
SET @ligado := (SELECT `value` FROM tb_setting WHERE `key` = 'sla_business_hours_enabled' LIMIT 1);

-- Marca onde o registo estava ANTES desta execução, para o resumo mostrar só
-- o que esta passagem mudou. (Uma janela de tempo não serve: numa segunda
-- passagem, feita logo a seguir, voltaria a contar as linhas da primeira e
-- daria a entender que houve alterações que não houve.)
SET @antes_desta_execucao := (SELECT IFNULL(MAX(id), 0) FROM tb_sla_pause_recalc_log);

SET @sql := IF(@ligado = '1', 'CALL sp_ops_recalcular_pausas_sla()', 'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- Resumo DESTA execução. Na segunda passagem dá 0 corrigidos: é idempotente.
SELECT
    IF(@ligado = '1', 'Recálculo executado', 'IGNORADO: o horário de atendimento está desligado') AS estado,
    (SELECT COUNT(*) FROM tb_sla_pause_recalc_log
      WHERE id > @antes_desta_execucao) AS processos_corrigidos,
    (SELECT IFNULL(SUM(minutos_antes), 0) FROM tb_sla_pause_recalc_log
      WHERE id > @antes_desta_execucao) AS minutos_pausa_antes,
    (SELECT IFNULL(SUM(minutos_depois), 0) FROM tb_sla_pause_recalc_log
      WHERE id > @antes_desta_execucao) AS minutos_pausa_depois;

-- Detalhe: que processos mudaram nesta execução e quanto.
SELECT p.process_number AS processo,
       l.minutos_antes,
       l.minutos_depois,
       l.minutos_depois - l.minutos_antes AS diferenca
  FROM tb_sla_pause_recalc_log l
  JOIN tb_process p ON p.id = l.process_id
 WHERE l.id > @antes_desta_execucao
 ORDER BY ABS(l.minutos_depois - l.minutos_antes) DESC;

-- As funções ficam instaladas (não custam nada e servem para conferir contas
-- à mão, ex.: SELECT fn_ops_minutos_uteis('2026-08-25 17:16', '2026-08-26 08:37')).
DROP PROCEDURE IF EXISTS sp_ops_recalcular_pausas_sla;
