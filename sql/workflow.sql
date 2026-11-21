-- Workflow de aprovação configurável (níveis + solicitações + histórico).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/workflow.sql
CREATE TABLE IF NOT EXISTS workflow_niveis (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT NOT NULL,
    ordem       INT NOT NULL DEFAULT 1,
    nome        VARCHAR(80) NOT NULL,             -- ex.: 'Supervisor', 'Gerente', 'Diretor', 'Financeiro'
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wfn_empresa (empresa_id, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_solicitacoes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT NOT NULL,
    titulo       VARCHAR(160) NOT NULL,
    tipo         VARCHAR(60) NULL,                -- ex.: 'Compra', 'Despesa', 'Manutenção'
    valor        DECIMAL(14,2) NULL,
    descricao    VARCHAR(500) NULL,
    status       ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
    nivel_atual  INT NOT NULL DEFAULT 1,          -- ordem do nível atual
    solicitante  VARCHAR(120) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wfs_empresa (empresa_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_historico (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    solicitacao_id  INT NOT NULL,
    empresa_id      INT NOT NULL,
    nivel_ordem     INT NULL,
    nivel_nome      VARCHAR(80) NULL,
    decisao         ENUM('aprovado','rejeitado') NOT NULL,
    aprovador       VARCHAR(120) NULL,
    comentario      VARCHAR(300) NULL,
    data            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wfh_sol (solicitacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
