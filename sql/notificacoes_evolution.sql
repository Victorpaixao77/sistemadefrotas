-- Evolução do módulo de notificações (severidade, agrupamento, preferências, sync).
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/notificacoes_evolution.sql

ALTER TABLE notificacoes
  ADD COLUMN IF NOT EXISTS severidade VARCHAR(20) NOT NULL DEFAULT 'info' AFTER tipo,
  ADD COLUMN IF NOT EXISTS grupo_chave VARCHAR(120) NULL AFTER severidade,
  ADD COLUMN IF NOT EXISTS meta_json TEXT NULL AFTER ia_mensagem;

CREATE INDEX IF NOT EXISTS idx_notif_grupo ON notificacoes (empresa_id, grupo_chave, data_criacao);

CREATE TABLE IF NOT EXISTS notificacoes_preferencias (
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    tipos_desativados JSON NULL,
    email_digest TINYINT(1) NOT NULL DEFAULT 0,
    email_digest_hora TIME NOT NULL DEFAULT '08:00:00',
    PRIMARY KEY (usuario_id, empresa_id),
    KEY idx_notif_pref_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacoes_sync_controle (
    empresa_id INT NOT NULL,
    chave VARCHAR(64) NOT NULL,
    ultima_execucao DATETIME NOT NULL,
    PRIMARY KEY (empresa_id, chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacoes_digest_envio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    data_ref DATE NOT NULL,
    enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_digest_dia (usuario_id, empresa_id, data_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
