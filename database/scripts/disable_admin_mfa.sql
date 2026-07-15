-- Desativa o MFA do utilizador "admin" diretamente na base de dados
-- (uso: quando o utilizador ficou trancado fora por perder o
-- autenticador/telemóvel e precisa de voltar a entrar sem o código).
--
-- Como usar no phpMyAdmin: abrir a base de dados do OPS -> aba "SQL" ->
-- colar este script -> Executar.

UPDATE tb_user
SET mfa_enabled = 0,
    mfa_secret = NULL
WHERE username = 'admin';

-- Remove os dispositivos "confiáveis" guardados para esse utilizador,
-- para não ficarem referências a um MFA que já não existe.
DELETE tdd FROM tb_mfa_trusted_device tdd
JOIN tb_user u ON u.id = tdd.user_id
WHERE u.username = 'admin';
