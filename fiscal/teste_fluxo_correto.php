<?php
/**
 * 🚛 TESTE DO FLUXO CORRETO DE DOCUMENTOS FISCAIS
 * 📋 Sistema de Gestão de Frotas - Fluxo: Receber NF-e → Criar CT-e → Criar MDF-e
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🚛 Teste do Fluxo Correto de Documentos Fiscais</h1>";
echo "<p><strong>✅ FLUXO CORRETO:</strong> Sistema de frota NÃO emite NF-e, apenas recebe e emite CT-e/MDF-e</p>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    echo "<h2>📋 1. Receber NF-e do Cliente</h2>";
    echo "<p>Simulando recebimento de NF-e do cliente...</p>";
    
    // Simular recebimento de NF-e
    $url = 'http://localhost/sistema-frotas/fiscal/api/documentos_fiscais_v2.php';
    $data = [
        'action' => 'receber_nfe',
        'numero_nfe' => '001',
        'serie_nfe' => '1',
        'chave_acesso' => '43250800000000000191550010000000011234567890',
        'cliente_remetente' => 'Empresa ABC Ltda',
        'cliente_destinatario' => 'Empresa XYZ Ltda',
        'valor_carga' => '1500.00',
        'peso_carga' => '500.00',
        'volumes' => '10'
    ];
    
    echo "<p><strong>Dados da NF-e:</strong></p>";
    echo "<ul>";
    echo "<li>Número: {$data['numero_nfe']}</li>";
    echo "<li>Série: {$data['serie_nfe']}</li>";
    echo "<li>Chave: {$data['chave_acesso']}</li>";
    echo "<li>Remetente: {$data['cliente_remetente']}</li>";
    echo "<li>Destinatário: {$data['cliente_destinatario']}</li>";
    echo "<li>Valor: R$ {$data['valor_carga']}</li>";
    echo "<li>Peso: {$data['peso_carga']} kg</li>";
    echo "<li>Volumes: {$data['volumes']}</li>";
    echo "</ul>";
    
    // Fazer a chamada real
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<h3>📥 Resposta da API:</h3>";
    echo "<p><strong>HTTP Code:</strong> {$http_code}</p>";
    
    if ($error) {
        echo "<p>❌ <strong>Erro cURL:</strong> {$error}</p>";
    } else {
        echo "<p>✅ <strong>Resposta:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Tentar decodificar JSON
        $json_data = json_decode($response, true);
        if ($json_data) {
            if (isset($json_data['success']) && $json_data['success']) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                echo "<strong>✅ NF-e recebida com sucesso!</strong><br>";
                echo "ID: " . ($json_data['nfe_id'] ?? 'N/A') . "<br>";
                echo "Status: " . ($json_data['status'] ?? 'N/A');
                echo "</div>";
                
                $nfe_id = $json_data['nfe_id'];
                
                // Agora testar criação de CT-e
                echo "<h2>🚛 2. Criar CT-e (Conhecimento de Transporte)</h2>";
                echo "<p>Simulando criação de CT-e para transportar a NF-e...</p>";
                
                $data_cte = [
                    'action' => 'criar_cte',
                    'nfe_ids[]' => $nfe_id,
                    'veiculo_id' => '1',
                    'motorista_id' => '1',
                    'origem' => 'São Paulo, SP',
                    'destino' => 'Rio de Janeiro, RJ',
                    'valor_frete' => '250.00',
                    'peso_total' => '500.00',
                    'volumes_total' => '10'
                ];
                
                echo "<p><strong>Dados do CT-e:</strong></p>";
                echo "<ul>";
                echo "<li>NF-e ID: {$nfe_id}</li>";
                echo "<li>Veículo: {$data_cte['veiculo_id']}</li>";
                echo "<li>Motorista: {$data_cte['motorista_id']}</li>";
                echo "<li>Origem: {$data_cte['origem']}</li>";
                echo "<li>Destino: {$data_cte['destino']}</li>";
                echo "<li>Valor Frete: R$ {$data_cte['valor_frete']}</li>";
                echo "<li>Peso: {$data_cte['peso_total']} kg</li>";
                echo "<li>Volumes: {$data_cte['volumes_total']}</li>";
                echo "</ul>";
                
                // Fazer a chamada para criar CT-e
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_cte));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
                
                $response_cte = curl_exec($ch);
                $http_code_cte = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error_cte = curl_error($ch);
                curl_close($ch);
                
                echo "<h3>📥 Resposta da API (CT-e):</h3>";
                echo "<p><strong>HTTP Code:</strong> {$http_code_cte}</p>";
                
                if ($error_cte) {
                    echo "<p>❌ <strong>Erro cURL:</strong> {$error_cte}</p>";
                } else {
                    echo "<p>✅ <strong>Resposta:</strong></p>";
                    echo "<pre>" . htmlspecialchars($response_cte) . "</pre>";
                    
                    $json_cte = json_decode($response_cte, true);
                    if ($json_cte && isset($json_cte['success']) && $json_cte['success']) {
                        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                        echo "<strong>✅ CT-e criado com sucesso!</strong><br>";
                        echo "ID: " . ($json_cte['cte_id'] ?? 'N/A') . "<br>";
                        echo "Número: " . ($json_cte['numero_cte'] ?? 'N/A') . "<br>";
                        echo "Status: " . ($json_cte['status'] ?? 'N/A');
                        echo "</div>";
                        
                        $cte_id = $json_cte['cte_id'];
                        
                        // Agora testar envio para SEFAZ
                        echo "<h2>🚀 3. Enviar CT-e para SEFAZ</h2>";
                        echo "<p>Simulando envio do CT-e para autorização...</p>";
                        
                        $data_sefaz = [
                            'action' => 'enviar_sefaz',
                            'id' => $cte_id,
                            'tipo_documento' => 'cte'
                        ];
                        
                        // Fazer a chamada para enviar para SEFAZ
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_sefaz));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/x-www-form-urlencoded'
                        ]);
                        
                        $response_sefaz = curl_exec($ch);
                        $http_code_sefaz = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error_sefaz = curl_error($ch);
                        curl_close($ch);
                        
                        echo "<h3>📥 Resposta da API (SEFAZ):</h3>";
                        echo "<p><strong>HTTP Code:</strong> {$http_code_sefaz}</p>";
                        
                        if ($error_sefaz) {
                            echo "<p>❌ <strong>Erro cURL:</strong> {$error_sefaz}</p>";
                        } else {
                            echo "<p>✅ <strong>Resposta:</strong></p>";
                            echo "<pre>" . htmlspecialchars($response_sefaz) . "</pre>";
                            
                            $json_sefaz = json_decode($response_sefaz, true);
                            if ($json_sefaz && isset($json_sefaz['success']) && $json_sefaz['success']) {
                                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                                echo "<strong>✅ CT-e enviado para SEFAZ com sucesso!</strong><br>";
                                echo "Status: " . ($json_sefaz['status'] ?? 'N/A') . "<br>";
                                echo "Protocolo: " . ($json_sefaz['protocolo'] ?? 'N/A');
                                echo "</div>";
                                
                                echo "<h2>🎉 FLUXO COMPLETO TESTADO COM SUCESSO!</h2>";
                                echo "<div style='background: #e7f3ff; color: #0c5460; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
                                echo "<h3>📋 Resumo do Fluxo:</h3>";
                                echo "<ol>";
                                echo "<li><strong>✅ NF-e recebida</strong> do cliente (ID: {$nfe_id})</li>";
                                echo "<li><strong>✅ CT-e criado</strong> para transportar a NF-e (ID: {$cte_id})</li>";
                                echo "<li><strong>✅ CT-e enviado</strong> para SEFAZ e autorizado</li>";
                                echo "</ol>";
                                echo "<p><strong>💡 IMPORTANTE:</strong> Este é o fluxo correto para um sistema de frota!</p>";
                                echo "</div>";
                                
                            } else {
                                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                                echo "<strong>❌ Erro ao enviar para SEFAZ:</strong> " . ($json_sefaz['error'] ?? 'Erro desconhecido');
                                echo "</div>";
                            }
                        }
                        
                    } else {
                        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                        echo "<strong>❌ Erro ao criar CT-e:</strong> " . ($json_cte['error'] ?? 'Erro desconhecido');
                        echo "</div>";
                    }
                }
                
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                echo "<strong>❌ Erro ao receber NF-e:</strong> " . ($json_data['error'] ?? 'Erro desconhecido');
                echo "</div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<p><a href='pages/nfe.php'>📄 Página Principal de Gestão Fiscal</a></p>";
echo "<p><a href='teste_sefaz.php'>🚀 Teste de Integração SEFAZ</a></p>";
echo "<p><a href='teste_visualizacao.php'>👁️ Teste de Visualização</a></p>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    h2 { color: #666; margin-top: 30px; }
    h3 { color: #888; margin-top: 20px; }
    p { margin: 10px 0; }
    ul, ol { margin: 10px 0; padding-left: 20px; }
    li { margin: 5px 0; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    hr { margin: 30px 0; border: none; border-top: 1px solid #ddd; }
</style>

