<?php
/**
 * 🔧 SCRIPT PARA CRIAR TABELAS FISCAIS NECESSÁRIAS
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

echo "<h1>🔧 Verificação e Criação de Tabelas Fiscais</h1>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar se a tabela empresa_clientes existe
    $stmt = $conn->query("SHOW TABLES LIKE 'empresa_clientes'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'empresa_clientes' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS empresa_clientes (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            empresa_adm_id INT(11) NOT NULL,
            razao_social VARCHAR(255) NOT NULL,
            nome_fantasia VARCHAR(255),
            cnpj VARCHAR(18) NOT NULL,
            inscricao_estadual VARCHAR(50),
            telefone VARCHAR(20),
            email VARCHAR(255),
            endereco VARCHAR(255),
            cidade VARCHAR(100),
            estado VARCHAR(2),
            cep VARCHAR(10),
            responsavel VARCHAR(100),
            logo VARCHAR(255),
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('ativo', 'inativo') DEFAULT 'ativo',
            INDEX idx_empresa_adm (empresa_adm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'empresa_clientes' criada com sucesso</p>";
        
        // Inserir cliente padrão
        $stmt = $conn->prepare("INSERT INTO empresa_clientes (empresa_adm_id, razao_social, nome_fantasia, cnpj, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([1, 'Cliente Padrão LTDA', 'Cliente Padrão', '00.000.000/0001-00', 'ativo']);
        echo "<p>✅ Cliente padrão inserido</p>";
    } else {
        echo "<p>✅ Tabela 'empresa_clientes' já existe</p>";
    }
    
    // Verificar se a tabela fiscal_nfe_clientes existe
    $stmt = $conn->query("SHOW TABLES LIKE 'fiscal_nfe_clientes'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'fiscal_nfe_clientes' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS fiscal_nfe_clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            numero_nfe VARCHAR(20) NOT NULL,
            serie_nfe VARCHAR(10),
            chave_acesso VARCHAR(44) UNIQUE,
            data_emissao DATE NOT NULL,
            data_entrada DATE,
            cliente_cnpj VARCHAR(18),
            cliente_razao_social VARCHAR(255),
            cliente_nome_fantasia VARCHAR(255),
            valor_total DECIMAL(15,2) NOT NULL,
            status ENUM('pendente', 'autorizada', 'cancelada', 'denegada', 'inutilizada') DEFAULT 'pendente',
            protocolo_autorizacao VARCHAR(50),
            xml_nfe LONGTEXT,
            pdf_nfe VARCHAR(255),
            observacoes TEXT,
            hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
            status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE,
            INDEX idx_empresa (empresa_id),
            INDEX idx_numero (numero_nfe),
            INDEX idx_chave (chave_acesso),
            INDEX idx_status (status),
            INDEX idx_data_emissao (data_emissao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notas fiscais recebidas dos clientes'";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'fiscal_nfe_clientes' criada com sucesso</p>";
    } else {
        echo "<p>✅ Tabela 'fiscal_nfe_clientes' já existe</p>";
    }
    
    // Verificar se a tabela fiscal_nfe_itens existe
    $stmt = $conn->query("SHOW TABLES LIKE 'fiscal_nfe_itens'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'fiscal_nfe_itens' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS fiscal_nfe_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nfe_id INT NOT NULL,
            codigo_produto VARCHAR(50),
            descricao_produto TEXT NOT NULL,
            ncm VARCHAR(10),
            cfop VARCHAR(4),
            unidade_comercial VARCHAR(10),
            quantidade_comercial DECIMAL(15,4) NOT NULL,
            valor_unitario DECIMAL(15,4) NOT NULL,
            valor_total_item DECIMAL(15,2) NOT NULL,
            peso_bruto DECIMAL(10,3),
            peso_liquido DECIMAL(10,3),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nfe_id) REFERENCES fiscal_nfe_clientes(id) ON DELETE CASCADE,
            INDEX idx_nfe (nfe_id),
            INDEX idx_codigo (codigo_produto),
            INDEX idx_ncm (ncm)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens das notas fiscais dos clientes'";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'fiscal_nfe_itens' criada com sucesso</p>";
    } else {
        echo "<p>✅ Tabela 'fiscal_nfe_itens' já existe</p>";
    }
    
    // Verificar se a tabela sequencias_documentos existe
    $stmt = $conn->query("SHOW TABLES LIKE 'sequencias_documentos'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'sequencias_documentos' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS sequencias_documentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            tipo_documento ENUM('NFE', 'CTE', 'MDFE') NOT NULL,
            serie VARCHAR(10) NOT NULL DEFAULT '1',
            ultimo_numero INT DEFAULT 0,
            proximo_numero INT DEFAULT 1,
            ano_exercicio INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE,
            UNIQUE KEY uk_sequencia (empresa_id, tipo_documento, serie, ano_exercicio),
            INDEX idx_empresa (empresa_id),
            INDEX idx_tipo (tipo_documento),
            INDEX idx_ano (ano_exercicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sequências de numeração para documentos fiscais'";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'sequencias_documentos' criada com sucesso</p>";
        
        // Inserir sequências padrão para o ano atual
        $ano_atual = date('Y');
        $empresa_id = 1; // Assumindo que o cliente padrão tem ID 1
        
        $stmt = $conn->prepare("INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$empresa_id, 'NFE', '1', 0, 1, $ano_atual]);
        $stmt->execute([$empresa_id, 'CTE', '1', 0, 1, $ano_atual]);
        $stmt->execute([$empresa_id, 'MDFE', '1', 0, 1, $ano_atual]);
        echo "<p>✅ Sequências padrão criadas para o ano $ano_atual</p>";
    } else {
        echo "<p>✅ Tabela 'sequencias_documentos' já existe</p>";
    }
    
    // Verificar se a tabela fiscal_cte existe
    $stmt = $conn->query("SHOW TABLES LIKE 'fiscal_cte'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'fiscal_cte' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS fiscal_cte (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            numero_cte VARCHAR(20) NOT NULL,
            serie_cte VARCHAR(10),
            chave_acesso VARCHAR(44) UNIQUE,
            data_emissao DATE NOT NULL,
            tipo_servico ENUM('normal', 'complemento', 'anulacao', 'substituicao') DEFAULT 'normal',
            natureza_operacao VARCHAR(100),
            protocolo_autorizacao VARCHAR(50),
            status ENUM('pendente', 'autorizado', 'cancelado', 'denegado', 'inutilizado') DEFAULT 'pendente',
            valor_total DECIMAL(15,2) NOT NULL,
            peso_total DECIMAL(10,3),
            origem_estado VARCHAR(2),
            origem_cidade VARCHAR(100),
            destino_estado VARCHAR(2),
            destino_cidade VARCHAR(100),
            xml_cte LONGTEXT,
            pdf_cte VARCHAR(255),
            observacoes TEXT,
            hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
            status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE,
            INDEX idx_empresa (empresa_id),
            INDEX idx_numero (numero_cte),
            INDEX idx_chave (chave_acesso),
            INDEX idx_status (status),
            INDEX idx_data_emissao (data_emissao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conhecimentos de Transporte Eletrônico'";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'fiscal_cte' criada com sucesso</p>";
    } else {
        echo "<p>✅ Tabela 'fiscal_cte' já existe</p>";
    }
    
    // Verificar se a tabela fiscal_mdfe existe
    $stmt = $conn->query("SHOW TABLES LIKE 'fiscal_mdfe'");
    if ($stmt->rowCount() == 0) {
        echo "<p>❌ Tabela 'fiscal_mdfe' não encontrada. Criando...</p>";
        
        $sql = "CREATE TABLE IF NOT EXISTS fiscal_mdfe (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            numero_mdfe VARCHAR(20) NOT NULL,
            serie_mdfe VARCHAR(10),
            chave_acesso VARCHAR(44) UNIQUE,
            data_emissao DATE NOT NULL,
            tipo_emissor ENUM('prestador', 'transportador', 'expedidor', 'recebedor', 'destinatario') DEFAULT 'transportador',
            protocolo_autorizacao VARCHAR(50),
            status ENUM('pendente', 'autorizado', 'cancelado', 'denegado', 'inutilizado') DEFAULT 'pendente',
            valor_total DECIMAL(15,2) NOT NULL,
            peso_total DECIMAL(10,3),
            qtd_documentos INT DEFAULT 0,
            xml_mdfe LONGTEXT,
            pdf_mdfe VARCHAR(255),
            observacoes TEXT,
            hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
            status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE,
            INDEX idx_empresa (empresa_id),
            INDEX idx_numero (numero_mdfe),
            INDEX idx_chave (chave_acesso),
            INDEX idx_status (status),
            INDEX idx_data_emissao (data_emissao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manifestos de Documentos Fiscais Eletrônicos'";
        
        $conn->exec($sql);
        echo "<p>✅ Tabela 'fiscal_mdfe' criada com sucesso</p>";
    } else {
        echo "<p>✅ Tabela 'fiscal_mdfe' já existe</p>";
    }
    
    echo "<h2>🎉 Todas as tabelas fiscais foram verificadas e criadas com sucesso!</h2>";
    echo "<p><a href='../pages/nfe.php'>← Voltar para a página NFe</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
