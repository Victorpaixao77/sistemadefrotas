-- Centro de Inteligência / Copiloto de Operações
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/ia_copilot.sql

CREATE TABLE IF NOT EXISTS ia_empresa_memoria (
    empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
    preferencias JSON NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    intent VARCHAR(64) NOT NULL DEFAULT '',
    agente VARCHAR(32) NOT NULL DEFAULT '',
    pergunta TEXT NULL,
    via VARCHAR(32) NULL,
    confianca_pct TINYINT UNSIGNED NULL,
    fontes JSON NULL,
    tempo_ms INT UNSIGNED NULL,
    tokens_estimados INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_aud_empresa (empresa_id, created_at),
    INDEX idx_ia_aud_intent (empresa_id, intent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_conversa_mensagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    role ENUM('user','assistant') NOT NULL,
    conteudo TEXT NOT NULL,
    intent VARCHAR(64) NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_msg_empresa (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_cache_consulta (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    cache_key VARCHAR(191) NOT NULL,
    payload JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ia_cache (empresa_id, cache_key),
    INDEX idx_ia_cache_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho VARCHAR(512) NOT NULL,
    mime VARCHAR(128) NULL,
    texto_extraido MEDIUMTEXT NULL,
    status ENUM('processando','pronto','erro') NOT NULL DEFAULT 'processando',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_doc_empresa (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_documento_chunks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    documento_id BIGINT UNSIGNED NOT NULL,
    empresa_id INT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
    chunk_text TEXT NOT NULL,
    INDEX idx_ia_chunk_doc (documento_id),
    INDEX idx_ia_chunk_empresa (empresa_id),
    FULLTEXT KEY ft_ia_chunk (chunk_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_acoes_pendentes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    tipo VARCHAR(64) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    resumo TEXT NULL,
    params JSON NOT NULL,
    status ENUM('pendente','confirmada','cancelada','executada','erro') NOT NULL DEFAULT 'pendente',
    resultado TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    executed_at DATETIME NULL,
    INDEX idx_ia_acao_empresa (empresa_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
