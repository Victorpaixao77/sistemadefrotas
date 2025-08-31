<?php
/**
 * 🧪 TESTE DAS TABELAS FISCAIS
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🧪 Teste das Tabelas Fiscais</h1>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar tabelas existentes
    $tabelas = ['empresa_clientes', 'fiscal_nfe_clientes', 'fiscal_nfe_itens', 'sequencias_documentos'];
    
    foreach ($tabelas as $tabela) {
        $stmt = $conn->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Tabela '$tabela' existe</p>";
            
            // Contar registros
            $stmt = $conn->query("SELECT COUNT(*) as total FROM $tabela");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "<p>   - Total de registros: $total</p>";
        } else {
            echo "<p>❌ Tabela '$tabela' não encontrada</p>";
        }
    }
    
    // Testar inserção de NF-e
    echo "<h2>🧪 Testando Criação de NF-e</h2>";
    
    // Verificar se existe empresa_id válido
    $stmt = $conn->query("SELECT id, razao_social FROM empresa_clientes LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        echo "<p>✅ Empresa encontrada: {$empresa['razao_social']} (ID: {$empresa['id']})</p>";
        
        // Testar criação de NF-e
        $empresa_id = $empresa['id'];
        
        // Verificar sequência
        $stmt = $conn->prepare("
            SELECT proximo_numero FROM sequencias_documentos 
            WHERE empresa_id = ? AND tipo_documento = 'NFE' AND serie = '1' AND ano_exercicio = ?
        ");
        $ano_atual = date('Y');
        $stmt->execute([$empresa_id, $ano_atual]);
        $sequencia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sequencia) {
            echo "<p>✅ Sequência encontrada: próximo número = {$sequencia['proximo_numero']}</p>";
        } else {
            echo "<p>⚠️ Sequência não encontrada, criando...</p>";
            
            $stmt = $conn->prepare("
                INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio)
                VALUES (?, 'NFE', '1', 0, 1, ?)
            ");
            $stmt->execute([$empresa_id, $ano_atual]);
            echo "<p>✅ Sequência criada</p>";
        }
        
        // Testar criação de NF-e
        $numero = $sequencia ? $sequencia['proximo_numero'] : 1;
        $chave = '43' . date('ym') . '00000000000191' . '55' . str_pad('1', 3, '0', STR_PAD_LEFT) . str_pad($numero, 9, '0', STR_PAD_LEFT) . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT) . '0';
        
        $stmt = $conn->prepare("
            INSERT INTO fiscal_nfe_clientes (
                empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao, 
                cliente_razao_social, cliente_cnpj, valor_total, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $empresa_id, $numero, '1', $chave, date('Y-m-d'),
            'Cliente Teste', '00.000.000/0001-00', 100.00, 'pendente'
        ]);
        
        $nfe_id = $conn->lastInsertId();
        echo "<p>✅ NF-e criada com sucesso! ID: $nfe_id</p>";
        
        // Inserir item
        $stmt = $conn->prepare("
            INSERT INTO fiscal_nfe_itens (
                nfe_id, codigo_produto, descricao_produto, ncm, cfop,
                unidade_comercial, quantidade_comercial, valor_unitario, valor_total_item
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $nfe_id, '001', 'Serviço de Transporte', '00000000', '1001',
            'UN', 1.0000, 100.00, 100.00
        ]);
        
        echo "<p>✅ Item da NF-e criado com sucesso!</p>";
        
        // Atualizar sequência
        $stmt = $conn->prepare("
            UPDATE sequencias_documentos 
            SET proximo_numero = proximo_numero + 1, ultimo_numero = ? 
            WHERE empresa_id = ? AND tipo_documento = 'NFE' AND serie = '1' AND ano_exercicio = ?
        ");
        $stmt->execute([$numero, $empresa_id, $ano_atual]);
        echo "<p>✅ Sequência atualizada</p>";
        
    } else {
        echo "<p>❌ Nenhuma empresa encontrada na tabela empresa_clientes</p>";
    }
    
    echo "<h2>🎉 Teste concluído!</h2>";
    echo "<p><a href='pages/nfe.php'>← Voltar para a página NFe</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
