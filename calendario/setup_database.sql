-- =====================================================
-- TABELAS PARA CALENDÁRIO COM CATEGORIAS
-- Sistema de Gestão de Frotas
-- =====================================================

-- 1. TABELA DE CATEGORIAS DO CALENDÁRIO
CREATE TABLE IF NOT EXISTS categorias_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao TEXT NULL,
    cor_padrao VARCHAR(7) DEFAULT '#3788d8',
    icone VARCHAR(50) DEFAULT 'fas fa-calendar',
    ativo BOOLEAN DEFAULT TRUE,
    empresa_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_id),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELA PRINCIPAL DO CALENDÁRIO
CREATE TABLE IF NOT EXISTS calendario_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    categoria_id INT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NULL,
    descricao TEXT NULL,
    cor VARCHAR(7) NULL,
    empresa_id INT NOT NULL,
    usuario_id INT NULL,
    pago BOOLEAN DEFAULT FALSE,
    data_pagamento DATETIME NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Campos para sincronização automática
    origem_tipo ENUM('multa', 'cnh', 'personalizado') DEFAULT 'personalizado',
    origem_id INT NULL, -- ID da multa ou motorista
    sincronizado BOOLEAN DEFAULT TRUE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_pago (pago),
    INDEX idx_origem (origem_tipo, origem_id),
    FOREIGN KEY (categoria_id) REFERENCES categorias_calendario(id) ON DELETE RESTRICT,
    FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. INSERIR CATEGORIAS PADRÃO
INSERT IGNORE INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) VALUES
('CNH', 'Vencimento de Carteira Nacional de Habilitação', '#ef4444', 'fas fa-id-card', 1),
('Multas', 'Vencimento de multas de trânsito', '#f59e0b', 'fas fa-exclamation-triangle', 1),
('Contas', 'Contas a pagar da empresa', '#3b82f6', 'fas fa-file-invoice-dollar', 1),
('Financiamento', 'Parcelas de financiamento', '#8b5cf6', 'fas fa-credit-card', 1),
('Manutenção', 'Manutenção preventiva de veículos', '#10b981', 'fas fa-tools', 1),
('Personalizado', 'Eventos criados pelo usuário', '#3788d8', 'fas fa-calendar-plus', 1);

-- =====================================================
-- TRIGGERS PARA SINCRONIZAÇÃO AUTOMÁTICA
-- =====================================================

-- 4. TRIGGER PARA MULTAS - INSERÇÃO
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_multa_insert_calendario
AFTER INSERT ON multas
FOR EACH ROW
BEGIN
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Buscar categoria de multas
    SELECT id INTO categoria_id FROM categorias_calendario 
    WHERE nome = 'Multas' AND empresa_id = NEW.empresa_id LIMIT 1;
    
    -- Se não existir categoria, criar
    IF categoria_id IS NULL THEN
        INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
        VALUES ('Multas', 'Vencimento de multas de trânsito', '#f59e0b', 'fas fa-exclamation-triangle', NEW.empresa_id);
        SET categoria_id = LAST_INSERT_ID();
    END IF;
    
    -- Se tem data de vencimento, criar evento no calendário
    IF NEW.vencimento IS NOT NULL THEN
        SET dias_para_vencimento = DATEDIFF(NEW.vencimento, CURRENT_DATE);
        
        -- Definir cor baseada na proximidade do vencimento
        IF dias_para_vencimento <= 7 THEN
            SET cor_evento = '#ef4444'; -- Vermelho (crítico)
        ELSEIF dias_para_vencimento <= 15 THEN
            SET cor_evento = '#f59e0b'; -- Amarelo (médio)
        ELSE
            SET cor_evento = '#3b82f6'; -- Azul (baixo)
        END IF;
        
        -- Criar título do evento
        SET titulo_evento = CONCAT('Multa: ', NEW.tipo_infracao, ' - R$ ', NEW.valor);
        
        -- Inserir evento no calendário
        INSERT INTO calendario_eventos (
            titulo, categoria_id, data_inicio, data_fim, descricao, cor,
            empresa_id, origem_tipo, origem_id, sincronizado
        ) VALUES (
            titulo_evento, categoria_id, NEW.vencimento, NEW.vencimento,
            CONCAT('Multa de ', NEW.tipo_infracao, ' - Veículo ID: ', NEW.veiculo_id, ' - Motorista ID: ', NEW.motorista_id),
            cor_evento, NEW.empresa_id, 'multa', NEW.id, TRUE
        );
    END IF;
END//
DELIMITER ;

-- 5. TRIGGER PARA MULTAS - ATUALIZAÇÃO
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_multa_update_calendario
AFTER UPDATE ON multas
FOR EACH ROW
BEGIN
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Buscar categoria de multas
    SELECT id INTO categoria_id FROM categorias_calendario 
    WHERE nome = 'Multas' AND empresa_id = NEW.empresa_id LIMIT 1;
    
    -- Se não existir categoria, criar
    IF categoria_id IS NULL THEN
        INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
        VALUES ('Multas', 'Vencimento de multas de trânsito', '#f59e0b', 'fas fa-exclamation-triangle', NEW.empresa_id);
        SET categoria_id = LAST_INSERT_ID();
    END IF;
    
    -- Se mudou a data de vencimento ou foi paga
    IF (NEW.vencimento != OLD.vencimento) OR (NEW.status_pagamento != OLD.status_pagamento) THEN
        -- Remover evento antigo se existir
        DELETE FROM calendario_eventos 
        WHERE origem_tipo = 'multa' AND origem_id = NEW.id;
        
        -- Se tem nova data de vencimento e não foi paga, criar novo evento
        IF NEW.vencimento IS NOT NULL AND NEW.status_pagamento != 'pago' THEN
            SET dias_para_vencimento = DATEDIFF(NEW.vencimento, CURRENT_DATE);
            
            -- Definir cor baseada na proximidade do vencimento
            IF dias_para_vencimento <= 7 THEN
                SET cor_evento = '#ef4444'; -- Vermelho (crítico)
            ELSEIF dias_para_vencimento <= 15 THEN
                SET cor_evento = '#f59e0b'; -- Amarelo (médio)
            ELSE
                SET cor_evento = '#3b82f6'; -- Azul (baixo)
            END IF;
            
            -- Criar título do evento
            SET titulo_evento = CONCAT('Multa: ', NEW.tipo_infracao, ' - R$ ', NEW.valor);
            
            -- Inserir novo evento no calendário
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, NEW.vencimento, NEW.vencimento,
                CONCAT('Multa de ', NEW.tipo_infracao, ' - Veículo ID: ', NEW.veiculo_id, ' - Motorista ID: ', NEW.motorista_id),
                cor_evento, NEW.empresa_id, 'multa', NEW.id, TRUE
            );
        END IF;
    END IF;
END//
DELIMITER ;

-- 6. TRIGGER PARA MULTAS - EXCLUSÃO
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_multa_delete_calendario
AFTER DELETE ON multas
FOR EACH ROW
BEGIN
    -- Remover evento do calendário quando multa for excluída
    DELETE FROM calendario_eventos 
    WHERE origem_tipo = 'multa' AND origem_id = OLD.id;
END//
DELIMITER ;

-- 7. TRIGGER PARA MOTORISTAS - INSERÇÃO/ATUALIZAÇÃO CNH
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_motorista_cnh_calendario
AFTER INSERT ON motoristas
FOR EACH ROW
BEGIN
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Só processar se tem data de validade da CNH
    IF NEW.data_validade_cnh IS NOT NULL THEN
        -- Buscar categoria de CNH
        SELECT id INTO categoria_id FROM categorias_calendario 
        WHERE nome = 'CNH' AND empresa_id = NEW.empresa_id LIMIT 1;
        
        -- Se não existir categoria, criar
        IF categoria_id IS NULL THEN
            INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
            VALUES ('CNH', 'Vencimento de Carteira Nacional de Habilitação', '#ef4444', 'fas fa-id-card', NEW.empresa_id);
            SET categoria_id = LAST_INSERT_ID();
        END IF;
        
        SET dias_para_vencimento = DATEDIFF(NEW.data_validade_cnh, CURRENT_DATE);
        
        -- Criar eventos para diferentes alertas
        IF dias_para_vencimento <= 30 THEN
            -- Vencimento em 30 dias ou menos - ALERTA VERMELHO
            SET cor_evento = '#ef4444';
            SET titulo_evento = CONCAT('CNH Vence em 30 dias: ', NEW.nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
            );
        ELSEIF dias_para_vencimento <= 60 THEN
            -- Vencimento em 60 dias ou menos - ALERTA AMARELO
            SET cor_evento = '#f59e0b';
            SET titulo_evento = CONCAT('CNH Vence em 60 dias: ', NEW.nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
            );
        ELSEIF dias_para_vencimento <= 90 THEN
            -- Vencimento em 90 dias ou menos - ALERTA AZUL
            SET cor_evento = '#3b82f6';
            SET titulo_evento = CONCAT('CNH Vence em 90 dias: ', NEW.nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
            );
        END IF;
    END IF;
END//
DELIMITER ;

-- 8. TRIGGER PARA MOTORISTAS - ATUALIZAÇÃO CNH
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_motorista_cnh_update_calendario
AFTER UPDATE ON motoristas
FOR EACH ROW
BEGIN
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Só processar se mudou a data de validade da CNH
    IF (NEW.data_validade_cnh != OLD.data_validade_cnh) OR (NEW.nome != OLD.nome) THEN
        -- Remover eventos antigos de CNH para este motorista
        DELETE FROM calendario_eventos 
        WHERE origem_tipo = 'cnh' AND origem_id = NEW.id;
        
        -- Se tem nova data de validade, criar novos eventos
        IF NEW.data_validade_cnh IS NOT NULL THEN
            -- Buscar categoria de CNH
            SELECT id INTO categoria_id FROM categorias_calendario 
            WHERE nome = 'CNH' AND empresa_id = NEW.empresa_id LIMIT 1;
            
            -- Se não existir categoria, criar
            IF categoria_id IS NULL THEN
                INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
                VALUES ('CNH', 'Vencimento de Carteira Nacional de Habilitação', '#ef4444', 'fas fa-id-card', NEW.empresa_id);
                SET categoria_id = LAST_INSERT_ID();
            END IF;
            
            SET dias_para_vencimento = DATEDIFF(NEW.data_validade_cnh, CURRENT_DATE);
            
            -- Criar eventos para diferentes alertas
            IF dias_para_vencimento <= 30 THEN
                -- Vencimento em 30 dias ou menos - ALERTA VERMELHO
                SET cor_evento = '#ef4444';
                SET titulo_evento = CONCAT('CNH Vence em 30 dias: ', NEW.nome);
                
                INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                    CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                    cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
                );
            ELSEIF dias_para_vencimento <= 60 THEN
                -- Vencimento em 60 dias ou menos - ALERTA AMARELO
                SET cor_evento = '#f59e0b';
                SET titulo_evento = CONCAT('CNH Vence em 60 dias: ', NEW.nome);
                
                INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                    CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                    cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
                );
            ELSEIF dias_para_vencimento <= 90 THEN
                -- Vencimento em 90 dias ou menos - ALERTA AZUL
                SET cor_evento = '#3b82f6';
                SET titulo_evento = CONCAT('CNH Vence em 90 dias: ', NEW.nome);
                
                INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    titulo_evento, categoria_id, NEW.data_validade_cnh, NEW.data_validade_cnh,
                    CONCAT('CNH do motorista ', NEW.nome, ' (Número: ', COALESCE(NEW.cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                    cor_evento, NEW.empresa_id, 'cnh', NEW.id, TRUE
                );
            END IF;
        END IF;
    END IF;
END//
DELIMITER ;

-- 9. TRIGGER PARA MOTORISTAS - EXCLUSÃO
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trigger_motorista_delete_calendario
AFTER DELETE ON motoristas
FOR EACH ROW
BEGIN
    -- Remover eventos de CNH quando motorista for excluído
    DELETE FROM calendario_eventos 
    WHERE origem_tipo = 'cnh' AND origem_id = OLD.id;
END//
DELIMITER ;

-- =====================================================
-- PROCEDIMENTO PARA SINCRONIZAÇÃO MANUAL
-- =====================================================

-- 10. PROCEDIMENTO PARA SINCRONIZAR TODAS AS MULTAS
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sincronizar_multas_calendario(IN empresa_id_param INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE multa_id INT;
    DECLARE multa_vencimento DATE;
    DECLARE multa_tipo VARCHAR(100);
    DECLARE multa_valor DECIMAL(10,2);
    DECLARE multa_veiculo_id INT;
    DECLARE multa_motorista_id INT;
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Cursor para todas as multas da empresa
    DECLARE multas_cursor CURSOR FOR
        SELECT id, vencimento, tipo_infracao, valor, veiculo_id, motorista_id
        FROM multas 
        WHERE empresa_id = empresa_id_param 
        AND vencimento IS NOT NULL 
        AND status_pagamento != 'pago';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Buscar categoria de multas
    SELECT id INTO categoria_id FROM categorias_calendario 
    WHERE nome = 'Multas' AND empresa_id = empresa_id_param LIMIT 1;
    
    -- Se não existir categoria, criar
    IF categoria_id IS NULL THEN
        INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
        VALUES ('Multas', 'Vencimento de multas de trânsito', '#f59e0b', 'fas fa-exclamation-triangle', empresa_id_param);
        SET categoria_id = LAST_INSERT_ID();
    END IF;
    
    -- Remover eventos antigos de multas
    DELETE FROM calendario_eventos 
    WHERE origem_tipo = 'multa' AND empresa_id = empresa_id_param;
    
    -- Abrir cursor
    OPEN multas_cursor;
    
    read_loop: LOOP
        FETCH multas_cursor INTO multa_id, multa_vencimento, multa_tipo, multa_valor, multa_veiculo_id, multa_motorista_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET dias_para_vencimento = DATEDIFF(multa_vencimento, CURRENT_DATE);
        
        -- Definir cor baseada na proximidade do vencimento
        IF dias_para_vencimento <= 7 THEN
            SET cor_evento = '#ef4444'; -- Vermelho (crítico)
        ELSEIF dias_para_vencimento <= 15 THEN
            SET cor_evento = '#f59e0b'; -- Amarelo (médio)
        ELSE
            SET cor_evento = '#3b82f6'; -- Azul (baixo)
        END IF;
        
        -- Criar título do evento
        SET titulo_evento = CONCAT('Multa: ', multa_tipo, ' - R$ ', multa_valor);
        
        -- Inserir evento no calendário
        INSERT INTO calendario_eventos (
            titulo, categoria_id, data_inicio, data_fim, descricao, cor,
            empresa_id, origem_tipo, origem_id, sincronizado
        ) VALUES (
            titulo_evento, categoria_id, multa_vencimento, multa_vencimento,
            CONCAT('Multa de ', multa_tipo, ' - Veículo ID: ', multa_veiculo_id, ' - Motorista ID: ', multa_motorista_id),
            cor_evento, empresa_id_param, 'multa', multa_id, TRUE
        );
    END LOOP;
    
    CLOSE multas_cursor;
END//
DELIMITER ;

-- 11. PROCEDIMENTO PARA SINCRONIZAR TODAS AS CNHs
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sincronizar_cnh_calendario(IN empresa_id_param INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE motorista_id INT;
    DECLARE motorista_nome VARCHAR(255);
    DECLARE motorista_cnh VARCHAR(20);
    DECLARE motorista_data_validade DATE;
    DECLARE categoria_id INT;
    DECLARE dias_para_vencimento INT;
    DECLARE cor_evento VARCHAR(7);
    DECLARE titulo_evento VARCHAR(255);
    
    -- Cursor para todos os motoristas da empresa com CNH válida
    DECLARE motoristas_cursor CURSOR FOR
        SELECT id, nome, cnh, data_validade_cnh
        FROM motoristas 
        WHERE empresa_id = empresa_id_param 
        AND data_validade_cnh IS NOT NULL 
        AND data_validade_cnh >= CURRENT_DATE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Buscar categoria de CNH
    SELECT id INTO categoria_id FROM categorias_calendario 
    WHERE nome = 'CNH' AND empresa_id = empresa_id_param LIMIT 1;
    
    -- Se não existir categoria, criar
    IF categoria_id IS NULL THEN
        INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
        VALUES ('CNH', 'Vencimento de Carteira Nacional de Habilitação', '#ef4444', 'fas fa-id-card', empresa_id_param);
        SET categoria_id = LAST_INSERT_ID();
    END IF;
    
    -- Remover eventos antigos de CNH
    DELETE FROM calendario_eventos 
    WHERE origem_tipo = 'cnh' AND empresa_id = empresa_id_param;
    
    -- Abrir cursor
    OPEN motoristas_cursor;
    
    read_loop: LOOP
        FETCH motoristas_cursor INTO motorista_id, motorista_nome, motorista_cnh, motorista_data_validade;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET dias_para_vencimento = DATEDIFF(motorista_data_validade, CURRENT_DATE);
        
        -- Criar eventos para diferentes alertas
        IF dias_para_vencimento <= 30 THEN
            -- Vencimento em 30 dias ou menos - ALERTA VERMELHO
            SET cor_evento = '#ef4444';
            SET titulo_evento = CONCAT('CNH Vence em 30 dias: ', motorista_nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, motorista_data_validade, motorista_data_validade,
                CONCAT('CNH do motorista ', motorista_nome, ' (Número: ', COALESCE(motorista_cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, empresa_id_param, 'cnh', motorista_id, TRUE
            );
        ELSEIF dias_para_vencimento <= 60 THEN
            -- Vencimento em 60 dias ou menos - ALERTA AMARELO
            SET cor_evento = '#f59e0b';
            SET titulo_evento = CONCAT('CNH Vence em 60 dias: ', motorista_nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, motorista_data_validade, motorista_data_validade,
                CONCAT('CNH do motorista ', motorista_nome, ' (Número: ', COALESCE(motorista_cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, empresa_id_param, 'cnh', motorista_id, TRUE
            );
        ELSEIF dias_para_vencimento <= 90 THEN
            -- Vencimento em 90 dias ou menos - ALERTA AZUL
            SET cor_evento = '#3b82f6';
            SET titulo_evento = CONCAT('CNH Vence em 90 dias: ', motorista_nome);
            
            INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                titulo_evento, categoria_id, motorista_data_validade, motorista_data_validade,
                CONCAT('CNH do motorista ', motorista_nome, ' (Número: ', COALESCE(motorista_cnh, 'N/A'), ') vence em ', dias_para_vencimento, ' dias.'),
                cor_evento, empresa_id_param, 'cnh', motorista_id, TRUE
            );
        END IF;
    END LOOP;
    
    CLOSE motoristas_cursor;
END//
DELIMITER ;

-- =====================================================
-- COMO FUNCIONA:
-- 1. Eventos automáticos aparecem com data de vencimento
-- 2. Eventos vencidos ficam piscando/vermelhos
-- 3. Usuário clica e marca como "Pago"
-- 4. Evento muda de cor e para de piscar
-- 5. Data de pagamento é registrada automaticamente
-- 6. TRIGGERS detectam mudanças e atualizam calendário automaticamente
-- =====================================================

-- VERIFICAR SE AS TABELAS FORAM CRIADAS:
-- DESCRIBE categorias_calendario;
-- DESCRIBE calendario_eventos;

-- VERIFICAR DADOS:
-- SELECT * FROM categorias_calendario;
-- SELECT * FROM calendario_eventos;

-- VERIFICAR TRIGGERS:
-- SHOW TRIGGERS LIKE 'trigger_%';

-- VERIFICAR PROCEDIMENTOS:
-- SHOW PROCEDURE STATUS LIKE 'sincronizar_%';

-- SINCRONIZAR MANUALMENTE (se necessário):
-- CALL sincronizar_multas_calendario(1);
-- CALL sincronizar_cnh_calendario(1);
