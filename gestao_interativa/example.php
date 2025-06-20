<?php
/**
 * Exemplo de uso do módulo Gestão Interativa
 * 
 * Este arquivo demonstra como usar as principais funcionalidades
 * do módulo de gestão interativa de pneus.
 */

// Incluir autoloader
require_once 'src/autoload.php';

// Configurações
$config = require_once 'config/app.php';
$dbConfig = require_once 'config/database.php';

// Inicializar classes
use GestaoInterativa\Database\Database;
use GestaoInterativa\Models\Pneu;
use GestaoInterativa\Models\Veiculo;
use GestaoInterativa\Models\Status;
use GestaoInterativa\Repositories\PneuRepository;
use GestaoInterativa\Repositories\VeiculoRepository;
use GestaoInterativa\Repositories\StatusRepository;
use GestaoInterativa\Controllers\PneuController;
use GestaoInterativa\Validation\Validation;
use GestaoInterativa\Session\Session;
use GestaoInterativa\Exceptions\ErrorHandler;

try {
    // Inicializar banco de dados
    $database = new Database($dbConfig);
    $pdo = $database->getConnection();
    
    // Inicializar repositórios
    $pneuRepository = new PneuRepository($pdo, 1); // empresa_id = 1
    $veiculoRepository = new VeiculoRepository($pdo, 1);
    $statusRepository = new StatusRepository($pdo);
    
    // Inicializar controller
    $pneuController = new PneuController($pneuRepository, $veiculoRepository, $statusRepository);
    
    // Inicializar validação e sessão
    $validation = new Validation();
    $session = new Session();
    $session->start();
    
    // Configurar error handler
    $errorHandler = new ErrorHandler($config);
    $errorHandler->register();
    
    echo "=== Exemplo de Uso do Módulo Gestão Interativa ===\n\n";
    
    // 1. Criar um novo pneu
    echo "1. Criando um novo pneu...\n";
    $pneuData = [
        'marca' => 'Michelin',
        'modelo' => 'XZA2',
        'medida' => '295/80R22.5',
        'status_id' => 1
    ];
    
    // Validar dados
    $rules = [
        'marca' => 'required|max:50',
        'modelo' => 'required|max:50',
        'medida' => 'required|max:20',
        'status_id' => 'required|numeric'
    ];
    
    try {
        $validation->validate($pneuData, $rules);
        
        $pneu = new Pneu();
        $pneu->setMarca($pneuData['marca']);
        $pneu->setModelo($pneuData['modelo']);
        $pneu->setMedida($pneuData['medida']);
        $pneu->setStatusId($pneuData['status_id']);
        
        $pneuId = $pneuRepository->create($pneu);
        echo "   Pneu criado com ID: {$pneuId}\n\n";
        
    } catch (Exception $e) {
        echo "   Erro ao criar pneu: " . $e->getMessage() . "\n\n";
    }
    
    // 2. Buscar pneus disponíveis
    echo "2. Buscando pneus disponíveis...\n";
    $pneusDisponiveis = $pneuRepository->findByStatus('disponivel');
    echo "   Encontrados " . count($pneusDisponiveis) . " pneus disponíveis\n\n";
    
    // 3. Buscar veículos
    echo "3. Buscando veículos...\n";
    $veiculos = $veiculoRepository->findAll();
    echo "   Encontrados " . count($veiculos) . " veículos\n\n";
    
    // 4. Buscar status
    echo "4. Buscando status disponíveis...\n";
    $statuses = $statusRepository->findAll();
    echo "   Status encontrados:\n";
    foreach ($statuses as $status) {
        echo "   - ID: {$status->getId()}, Nome: {$status->getNome()}\n";
    }
    echo "\n";
    
    // 5. Exemplo de alocação de pneu
    if (!empty($pneusDisponiveis) && !empty($veiculos)) {
        echo "5. Exemplo de alocação de pneu...\n";
        $pneu = $pneusDisponiveis[0];
        $veiculo = $veiculos[0];
        
        echo "   Alocando pneu {$pneu->getMarca()} {$pneu->getModelo()} no veículo {$veiculo->getPlaca()}\n";
        
        // Simular alocação
        $pneu->setStatusId(2); // em_uso
        $pneuRepository->update($pneu);
        
        echo "   Pneu alocado com sucesso!\n\n";
    }
    
    // 6. Exemplo de busca com filtros
    echo "6. Exemplo de busca com filtros...\n";
    $filtros = [
        'marca' => 'Michelin',
        'status' => 'disponivel'
    ];
    
    $pneusFiltrados = $pneuRepository->findByFilters($filtros);
    echo "   Encontrados " . count($pneusFiltrados) . " pneus Michelin disponíveis\n\n";
    
    // 7. Exemplo de estatísticas
    echo "7. Estatísticas do sistema...\n";
    $totalPneus = $pneuRepository->count();
    $pneusEmUso = count($pneuRepository->findByStatus('em_uso'));
    $pneusManutencao = count($pneuRepository->findByStatus('manutencao'));
    
    echo "   Total de pneus: {$totalPneus}\n";
    echo "   Pneus em uso: {$pneusEmUso}\n";
    echo "   Pneus em manutenção: {$pneusManutencao}\n";
    echo "   Pneus disponíveis: " . ($totalPneus - $pneusEmUso - $pneusManutencao) . "\n\n";
    
    // 8. Exemplo de sessão
    echo "8. Exemplo de uso de sessão...\n";
    $session->set('ultima_acao', 'Consulta de pneus');
    $session->set('usuario_id', 1);
    
    echo "   Última ação: " . $session->get('ultima_acao') . "\n";
    echo "   Usuário ID: " . $session->get('usuario_id') . "\n\n";
    
    // 9. Exemplo de helpers
    echo "9. Exemplo de helpers...\n";
    $helpers = new \GestaoInterativa\Helpers\Helpers();
    
    $data = '2024-01-15';
    $valor = 1234.56;
    $telefone = '11987654321';
    
    echo "   Data formatada: " . $helpers::formatDate($data) . "\n";
    echo "   Valor formatado: " . $helpers::formatCurrency($valor) . "\n";
    echo "   Telefone formatado: " . $helpers::formatPhone($telefone) . "\n\n";
    
    echo "=== Exemplo concluído com sucesso! ===\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
} 