<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once 'analise_dados.php';
require_once 'alertas_inteligentes.php';
require_once 'recomendacoes.php';
require_once 'insights.php';
require_once 'notificacoes.php';
require_once 'config.php';

// Verifica autenticação
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Inicializa classes
$analise = new AnaliseDados($empresa_id);
$alertas = new AlertasInteligentes($empresa_id);
$recomendacoes = new Recomendacoes($empresa_id);
$insights = new Insights($empresa_id);
$notificacoes = new Notificacoes($empresa_id);
$config = new ConfiguracoesIA($empresa_id);

// Obtém método e rota
$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';

// Define headers
header('Content-Type: application/json');

// Função para retornar resposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Função para validar dados de entrada
function validateInput($data, $required = []) {
    $errors = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $errors[] = "Campo {$field} é obrigatório";
        }
    }
    return $errors;
}

// Rotas da API
switch ($route) {
    // Análise de Dados
    case 'analise/consumo':
        if ($method === 'GET') {
            $dados = $analise->analisarConsumoCombustivel();
            jsonResponse($dados);
        }
        break;

    case 'analise/manutencao':
        if ($method === 'GET') {
            $dados = $analise->analisarManutencoes();
            jsonResponse($dados);
        }
        break;

    case 'analise/rotas':
        if ($method === 'GET') {
            $dados = $analise->analisarRotas();
            jsonResponse($dados);
        }
        break;

    // Alertas
    case 'alertas':
        if ($method === 'GET') {
            $dados = $alertas->obterTodosAlertas();
            jsonResponse($dados);
        }
        break;

    case 'alertas/manutencao':
        if ($method === 'GET') {
            $dados = $alertas->verificarManutencaoPreventiva();
            jsonResponse($dados);
        }
        break;

    case 'alertas/documentos':
        if ($method === 'GET') {
            $dados = $alertas->verificarDocumentos();
            jsonResponse($dados);
        }
        break;

    case 'alertas/consumo':
        if ($method === 'GET') {
            $dados = $alertas->verificarConsumoCombustivel();
            jsonResponse($dados);
        }
        break;

    case 'alertas/rotas':
        if ($method === 'GET') {
            $dados = $alertas->verificarRotas();
            jsonResponse($dados);
        }
        break;

    // Recomendações
    case 'recomendacoes':
        if ($method === 'GET') {
            $dados = $recomendacoes->obterTodasRecomendacoes();
            jsonResponse($dados);
        }
        break;

    case 'recomendacoes/rotas':
        if ($method === 'GET') {
            $dados = $recomendacoes->gerarRecomendacoesRotas();
            jsonResponse($dados);
        }
        break;

    case 'recomendacoes/manutencao':
        if ($method === 'GET') {
            $dados = $recomendacoes->gerarRecomendacoesManutencao();
            jsonResponse($dados);
        }
        break;

    case 'recomendacoes/economia':
        if ($method === 'GET') {
            $dados = $recomendacoes->gerarRecomendacoesEconomia();
            jsonResponse($dados);
        }
        break;

    case 'recomendacoes/seguranca':
        if ($method === 'GET') {
            $dados = $recomendacoes->gerarRecomendacoesSeguranca();
            jsonResponse($dados);
        }
        break;

    // Insights
    case 'insights':
        if ($method === 'GET') {
            $dados = $insights->obterTodosInsights();
            jsonResponse($dados);
        }
        break;

    case 'insights/consumo':
        if ($method === 'GET') {
            $dados = $insights->analisarPadroesConsumo();
            jsonResponse($dados);
        }
        break;

    case 'insights/manutencao':
        if ($method === 'GET') {
            $dados = $insights->analisarPadroesManutencao();
            jsonResponse($dados);
        }
        break;

    case 'insights/rotas':
        if ($method === 'GET') {
            $dados = $insights->analisarPadroesRotas();
            jsonResponse($dados);
        }
        break;

    case 'insights/custos':
        if ($method === 'GET') {
            $dados = $insights->analisarCustosOperacionais();
            jsonResponse($dados);
        }
        break;

    // Notificações
    case 'notificacoes':
        if ($method === 'GET') {
            $dados = $notificacoes->obterNotificacoesPendentes();
            jsonResponse($dados);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $errors = validateInput($data, ['tipo', 'mensagem']);
            
            if (!empty($errors)) {
                jsonResponse(['errors' => $errors], 400);
            }
            
            $result = $notificacoes->registrarNotificacao(
                $data['tipo'],
                $data['mensagem'],
                $data['prioridade'] ?? 'media',
                $data['dados'] ?? []
            );
            
            if ($result) {
                jsonResponse(['id' => $result]);
            } else {
                jsonResponse(['error' => 'Erro ao registrar notificação'], 500);
            }
        }
        break;

    case 'notificacoes/marcar-lida':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $errors = validateInput($data, ['id']);
            
            if (!empty($errors)) {
                jsonResponse(['errors' => $errors], 400);
            }
            
            $result = $notificacoes->marcarComoLida($data['id']);
            jsonResponse(['success' => $result]);
        }
        break;

    case 'notificacoes/marcar-todas-lidas':
        if ($method === 'POST') {
            $result = $notificacoes->marcarTodasComoLidas();
            jsonResponse(['success' => $result]);
        }
        break;

    case 'notificacoes/estatisticas':
        if ($method === 'GET') {
            $dados = $notificacoes->obterEstatisticas();
            jsonResponse($dados);
        }
        break;

    case 'notificacoes/historico':
        if ($method === 'GET') {
            $pagina = $_GET['pagina'] ?? 1;
            $por_pagina = $_GET['por_pagina'] ?? 20;
            
            $dados = $notificacoes->obterHistorico($pagina, $por_pagina);
            $total_paginas = $notificacoes->obterTotalPaginas($por_pagina);
            
            jsonResponse([
                'dados' => $dados,
                'pagina' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => $total_paginas
            ]);
        }
        break;

    // Configurações
    case 'configuracoes':
        if ($method === 'GET') {
            $dados = $config->obterConfiguracoes();
            jsonResponse($dados);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data)) {
                jsonResponse(['error' => 'Dados inválidos'], 400);
            }
            
            $result = $config->salvarConfiguracoes($data);
            jsonResponse(['success' => $result]);
        }
        break;

    case 'configuracoes/resetar':
        if ($method === 'POST') {
            $result = $config->resetarConfiguracoes();
            jsonResponse(['success' => $result]);
        }
        break;

    case 'configuracoes/atualizar':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $errors = validateInput($data, ['key', 'value']);
            
            if (!empty($errors)) {
                jsonResponse(['errors' => $errors], 400);
            }
            
            $result = $config->atualizarConfiguracao($data['key'], $data['value']);
            jsonResponse(['success' => $result]);
        }
        break;

    case 'configuracoes/remover':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $errors = validateInput($data, ['key']);
            
            if (!empty($errors)) {
                jsonResponse(['errors' => $errors], 400);
            }
            
            $result = $config->removerConfiguracao($data['key']);
            jsonResponse(['success' => $result]);
        }
        break;

    default:
        jsonResponse(['error' => 'Rota não encontrada'], 404);
        break;
} 