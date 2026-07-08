-- Presença em tempo real (Tela Operacional): última atividade do utilizador,
-- atualizada pelo middleware no máximo a cada 2 minutos.
SET NAMES utf8mb4;

ALTER TABLE tb_user
    ADD COLUMN last_activity_at DATETIME NULL AFTER last_login_at;
