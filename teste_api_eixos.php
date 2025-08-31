<?php
// Script para testar a API de eixos diretamente
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "=== TESTE DIRETO DA API DE EIXOS ===\n\n";

try {
    $conn = getConnection();
    
    // Buscar um veículo para teste
    $stmt = $conn->query("SELECT id, placa, modelo FROM veiculos LIMIT 1");
    $veiculo = $stmt->fetch();
    
    if (!$veiculo) {
        echo "❌ Nenhum veículo encontrado para teste\n";
        exit;
    }
    
    $veiculo_id = $veiculo['id'];
    echo "Testando com veículo: {$veiculo['placa']} - {$veiculo['modelo']} (ID: $veiculo_id)\n\n";
    
    // Simular a chamada da API
    echo "1. TESTANDO API: layout_completo\n";
    
    // Incluir a API diretamente
    ob_start();
    
    // Simular variáveis da sessão
    $_SESSION['empresa_id'] = 1; // Ajuste conforme necessário
    $_GET['action'] = 'layout_completo';
    $_GET['veiculo_id'] = $veiculo_id;
    
    // Incluir a API
    include 'gestao_interativa/api/eixos_veiculos.php';
    
    $output = ob_get_clean();
    
    echo "Resposta da API:\n";
    echo $output . "\n";
    
    // Tentar decodificar JSON
    $data = json_decode($output, true);
    if ($data) {
        echo "\nJSON decodificado:\n";
        print_r($data);
        
        if (isset($data['success']) && $data['success']) {
            echo "\n✅ API funcionando corretamente!\n";
            
            if (isset($data['layout'])) {
                $layout = $data['layout'];
                echo "\nDados do layout:\n";
                echo "- Eixos caminhão: " . count($layout['eixosCaminhao']) . "\n";
                echo "- Eixos carreta: " . count($layout['eixosCarreta']) . "\n";
                echo "- Pneus alocados: " . count($layout['pneusFlexAlocados']) . "\n";
            }
        } else {
            echo "\n❌ API retornou erro: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
        }
    } else {
        echo "\n❌ Erro ao decodificar JSON da API\n";
        echo "Resposta bruta: " . $output . "\n";
    }
    
    // 2. Testar adicionar eixo
    echo "\n\n2. TESTANDO API: adicionar_eixo\n";
    
    ob_start();
    
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'adicionar_eixo';
    $_POST = json_encode([
        'veiculo_id' => $veiculo_id,
        'tipo_veiculo' => 'caminhao',
        'quantidade_pneus' => 2
    ]);
    
    // Simular input JSON
    file_put_contents('php://input', $_POST);
    
    include 'gestao_interativa/api/eixos_veiculos.php';
    
    $output = ob_get_clean();
    
    echo "Resposta da API (adicionar eixo):\n";
    echo $output . "\n";
    
    $data = json_decode($output, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ Eixo adicionado com sucesso!\n";
    } else {
        echo "❌ Erro ao adicionar eixo: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 