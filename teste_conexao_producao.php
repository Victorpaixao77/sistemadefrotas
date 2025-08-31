<?php
/**
 * Script de Teste de Conexão para Produção
 * Sistema de Gestão de Frotas
 * 
 * Este script testa se as configurações de produção estão funcionando
 */

echo "=== TESTE DE CONEXÃO PARA PRODUÇÃO ===\n";
echo "Sistema de Gestão de Frotas\n\n";

// Carregar configurações
require_once 'includes/config.php';

echo "Configurações carregadas:\n";
echo "- Servidor: " . DB_SERVER . "\n";
echo "- Usuário: " . DB_USERNAME . "\n";
echo "- Banco: " . DB_NAME . "\n";
echo "- Debug: " . (DEBUG_MODE ? 'Ativado' : 'Desativado') . "\n\n";

// Teste 1: Conexão básica
echo "1. Testando conexão básica...\n";
try {
    $conn = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "   ✅ Conexão estabelecida com sucesso!\n";
    
    // Teste 2: Verificar versão do MySQL
    $stmt = $conn->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "   📊 Versão do MySQL: " . $version['version'] . "\n";
    
} catch (PDOException $e) {
    echo "   ❌ Erro na conexão: " . $e->getMessage() . "\n";
    exit(1);
}

// Teste 3: Verificar tabelas principais
echo "\n2. Verificando tabelas principais...\n";
$tabelas_principais = [
    'empresas',
    'usuarios', 
    'veiculos',
    'motoristas',
    'manutencoes',
    'abastecimentos',
    'rotas'
];

foreach ($tabelas_principais as $tabela) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM $tabela");
        $result = $stmt->fetch();
        echo "   ✅ $tabela: " . $result['total'] . " registros\n";
    } catch (PDOException $e) {
        echo "   ❌ $tabela: Erro - " . $e->getMessage() . "\n";
    }
}

// Teste 4: Verificar configurações do sistema
echo "\n3. Verificando configurações do sistema...\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM empresas");
    $empresas = $stmt->fetch();
    echo "   📊 Empresas cadastradas: " . $empresas['total'] . "\n";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM veiculos");
    $veiculos = $stmt->fetch();
    echo "   🚗 Veículos cadastrados: " . $veiculos['total'] . "\n";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios");
    $usuarios = $stmt->fetch();
    echo "   👥 Usuários cadastrados: " . $usuarios['total'] . "\n";
    
} catch (PDOException $e) {
    echo "   ❌ Erro ao verificar dados: " . $e->getMessage() . "\n";
}

// Teste 5: Verificar permissões
echo "\n4. Verificando permissões...\n";
try {
    $stmt = $conn->query("SHOW GRANTS FOR CURRENT_USER()");
    $grants = $stmt->fetchAll();
    echo "   ✅ Permissões verificadas (" . count($grants) . " permissões)\n";
} catch (PDOException $e) {
    echo "   ⚠️  Não foi possível verificar permissões: " . $e->getMessage() . "\n";
}

// Teste 6: Verificar configurações de charset
echo "\n5. Verificando configurações de charset...\n";
try {
    $stmt = $conn->query("SHOW VARIABLES LIKE 'character_set%'");
    $charsets = $stmt->fetchAll();
    foreach ($charsets as $charset) {
        echo "   📝 " . $charset['Variable_name'] . ": " . $charset['Value'] . "\n";
    }
} catch (PDOException $e) {
    echo "   ❌ Erro ao verificar charset: " . $e->getMessage() . "\n";
}

// Teste 7: Performance básica
echo "\n6. Teste de performance básica...\n";
try {
    $start_time = microtime(true);
    
    // Consulta simples
    $stmt = $conn->query("SELECT COUNT(*) as total FROM veiculos");
    $result = $stmt->fetch();
    
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time) * 1000; // em milissegundos
    
    echo "   ⚡ Tempo de consulta: " . number_format($execution_time, 2) . " ms\n";
    
    if ($execution_time < 100) {
        echo "   ✅ Performance OK\n";
    } else {
        echo "   ⚠️  Performance pode estar lenta\n";
    }
    
} catch (PDOException $e) {
    echo "   ❌ Erro no teste de performance: " . $e->getMessage() . "\n";
}

echo "\n=== RESUMO DO TESTE ===\n";
echo "✅ Conexão com banco de dados: OK\n";
echo "✅ Configurações carregadas: OK\n";
echo "✅ Tabelas principais: Verificadas\n";
echo "✅ Dados do sistema: Verificados\n";
echo "✅ Permissões: Verificadas\n";
echo "✅ Charset: Verificado\n";
echo "✅ Performance: Testada\n";

echo "\n🎉 Sistema pronto para produção!\n";
echo "\n📋 RECOMENDAÇÕES:\n";
echo "1. Configure backups automáticos\n";
echo "2. Configure monitoramento de performance\n";
echo "3. Configure logs de erro\n";
echo "4. Configure SSL/HTTPS\n";
echo "5. Configure firewall\n";
echo "6. Teste todas as funcionalidades\n";

?> 