-- Tokens seguros para "Lembrar-me" (substitui o cookie forjável base64(id:email)).
-- Padrão selector:validator — o cookie guarda selector + validator; o banco guarda
-- apenas o selector (indexado) e o HASH do validator. Assim um vazamento da tabela
-- não permite forjar cookies, e cada dispositivo pode ser revogado individualmente.
CREATE TABLE IF NOT EXISTS auth_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_usuario (usuario_id),
    KEY idx_remember_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
