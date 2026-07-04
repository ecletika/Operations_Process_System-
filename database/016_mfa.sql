-- Autenticação de dois fatores (MFA/2FA) por TOTP (app autenticadora).
SET NAMES utf8mb4;

ALTER TABLE tb_user
    ADD COLUMN mfa_secret  VARCHAR(64)  NULL AFTER password,
    ADD COLUMN mfa_enabled TINYINT(1)   NOT NULL DEFAULT 0 AFTER mfa_secret;

-- Dispositivos de confiança: depois de passar o MFA, o dispositivo fica
-- confiável durante N horas (pedir só uma vez por dia).
CREATE TABLE IF NOT EXISTS tb_mfa_trusted_device (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36)        NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    expires_at  DATETIME        NOT NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mfa_device_uuid (uuid),
    KEY idx_mfa_device_user (user_id),
    KEY idx_mfa_device_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parâmetros (Configurações).
INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'mfa_required', '0', 'Exigir MFA a todos os utilizadores no login (1=sim). Ative depois de testar com a sua conta.'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'mfa_required');

INSERT INTO tb_setting (uuid, `key`, `value`, description)
SELECT UUID(), 'mfa_trust_hours', '24', 'Horas que um dispositivo fica confiável após passar o MFA (pedir 1x por dia)'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_setting WHERE `key` = 'mfa_trust_hours');
