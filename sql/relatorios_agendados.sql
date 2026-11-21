-- Agendamento de envio automático de relatórios por e-mail.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/relatorios_agendados.sql
CREATE TABLE IF NOT EXISTS relatorios_agendados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    report_type VARCHAR(60) NOT NULL,
    frequencia ENUM('diario', 'semanal', 'mensal') NOT NULL DEFAULT 'mensal',
    destinatarios TEXT NOT NULL,
    formato ENUM('pdf', 'html') NOT NULL DEFAULT 'html',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_envio DATETIME NULL,
    proximo_envio DATETIME NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_emp (empresa_id, ativo),
    KEY idx_prox (proximo_envio, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
