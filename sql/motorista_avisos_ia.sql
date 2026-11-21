-- Avisos do gestor/Copiloto para o app motorista (polling em app_android/api/avisos.php)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/motorista_avisos_ia.sql

CREATE TABLE IF NOT EXISTS motorista_avisos_ia (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    mensagem TEXT NOT NULL,
    origem VARCHAR(40) NOT NULL DEFAULT 'ia_copilot',
    lido_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mot (motorista_id, empresa_id, lido_em),
    KEY idx_emp (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
