-- Tabela principal de notificações (multi-empresa)
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'alerta',
    severidade VARCHAR(20) NOT NULL DEFAULT 'info',
    grupo_chave VARCHAR(120) NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    ia_mensagem TEXT NULL,
    meta_json TEXT NULL,
    referencia_id INT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_notif_empresa_lida (empresa_id, lida, data_criacao),
    INDEX idx_notif_tipo_ref (empresa_id, tipo, referencia_id),
    INDEX idx_notif_grupo (empresa_id, grupo_chave, data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Evolução adicional: sql/notificacoes_evolution.sql (preferências, sync, digest)
