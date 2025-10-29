<?php
/**
 * API - Dashboard - Clientes Cadastrados por Mês
 * Retorna quantidade de clientes cadastrados por mês para o gráfico
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$empresa_id = obterEmpresaId();

try {
    // Buscar clientes cadastrados por mês nos últimos 12 meses
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(data_cadastro, '%Y-%m') as mes,
            COUNT(*) as total
        FROM seguro_clientes
        WHERE seguro_empresa_id = ?
        AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_cadastro, '%Y-%m')
        ORDER BY mes ASC
    ");
    $stmt->execute([$empresa_id]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não tiver dados, gerar estrutura vazia para os últimos 12 meses
    if (empty($dados)) {
        $dados = [];
        for ($i = 11; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("-$i months"));
            $dados[] = [
                'mes' => $mes,
                'total' => 0
            ];
        }
    } else {
        // Preencher meses faltantes com zero
        $mesesCompletos = [];
        $dataInicio = strtotime('-11 months');
        $dataFim = time();
        
        for ($i = $dataInicio; $i <= $dataFim; $i = strtotime('+1 month', $i)) {
            $mesAtual = date('Y-m', $i);
            $encontrado = false;
            
            foreach ($dados as $dado) {
                if ($dado['mes'] === $mesAtual) {
                    $mesesCompletos[] = $dado;
                    $encontrado = true;
                    break;
                }
            }
            
            if (!$encontrado) {
                $mesesCompletos[] = [
                    'mes' => $mesAtual,
                    'total' => 0
                ];
            }
        }
        
        $dados = $mesesCompletos;
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $dados,
        'total_meses' => count($dados)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados do gráfico: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

