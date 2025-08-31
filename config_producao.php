<?php
/**
 * Script de Configuração para Produção
 * Sistema de Gestão de Frotas
 * 
 * Este script automatiza a configuração do banco de dados para produção
 * Execute este script uma vez antes de fazer o deploy
 */

echo "=== CONFIGURAÇÃO PARA PRODUÇÃO ===\n";
echo "Sistema de Gestão de Frotas\n\n";

// Configurações de produção (ALTERE AQUI)
$config_producao = [
    'DB_SERVER' => 'localhost',           // Servidor de produção
    'DB_PORT' => '3306',                  // Porta do MySQL (geralmente 3306)
    'DB_USERNAME' => 'root',       // Usuário do banco de produção
    'DB_PASSWORD' => 'SenhaForte@2024',         // Senha do banco de produção
    'DB_NAME' => 'sistema_frotas',        // Nome do banco (altere se necessário)
    'URL_BASE' => 'http://frotec.online.com', // URL de produção
    'DEBUG_MODE' => false                 // Desabilitar debug em produção
];

// Configurações atuais (desenvolvimento)
$config_desenvolvimento = [
    'DB_SERVER' => 'localhost:3307',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    'DB_NAME' => 'sistema_frotas',
    'URL_BASE' => 'http://localhost/sistema-frotas',
    'DEBUG_MODE' => true
];

echo "Configurações atuais (desenvolvimento):\n";
foreach ($config_desenvolvimento as $key => $value) {
    echo "- $key: $value\n";
}

echo "\nConfigurações de produção (serão aplicadas):\n";
foreach ($config_producao as $key => $value) {
    echo "- $key: $value\n";
}

echo "\n⚠️  ATENÇÃO: Você alterou as configurações de produção acima?\n";
echo "Se não, edite o script e configure os valores corretos.\n\n";

echo "Deseja continuar? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 's') {
    echo "Operação cancelada.\n";
    exit;
}

echo "\n=== INICIANDO CONFIGURAÇÃO ===\n";

// Lista de arquivos para alterar
$arquivos = [
    'includes/config.php' => [
        'patterns' => [
            "/define\('DB_SERVER',\s*'[^']*'\);/" => "define('DB_SERVER', '{$config_producao['DB_SERVER']}:{$config_producao['DB_PORT']}');",
            "/define\('DB_USERNAME',\s*'[^']*'\);/" => "define('DB_USERNAME', '{$config_producao['DB_USERNAME']}');",
            "/define\('DB_PASSWORD',\s*'[^']*'\);/" => "define('DB_PASSWORD', '{$config_producao['DB_PASSWORD']}');",
            "/define\('DB_NAME',\s*'[^']*'\);/" => "define('DB_NAME', '{$config_producao['DB_NAME']}');",
            "/define\('DEBUG_MODE',\s*(true|false)\);/" => "define('DEBUG_MODE', " . ($config_producao['DEBUG_MODE'] ? 'true' : 'false') . ");"
        ]
    ],
    
    'includes/conexao.php' => [
        'patterns' => [
            "/\\\$host\s*=\s*'[^']*';/" => "\$host = '{$config_producao['DB_SERVER']}';",
            "/\\\$port\s*=\s*'[^']*';/" => "\$port = '{$config_producao['DB_PORT']}';",
            "/\\\$username\s*=\s*'[^']*';/" => "\$username = '{$config_producao['DB_USERNAME']}';",
            "/\\\$password\s*=\s*'[^']*';/" => "\$password = '{$config_producao['DB_PASSWORD']}';",
            "/\\\$dbname\s*=\s*'[^']*';/" => "\$dbname = '{$config_producao['DB_NAME']}';"
        ]
    ],
    
    'pages_motorista/db.php' => [
        'patterns' => [
            "/define\('DB_SERVER',\s*'[^']*'\);/" => "define('DB_SERVER', '{$config_producao['DB_SERVER']}:{$config_producao['DB_PORT']}');",
            "/define\('DB_USERNAME',\s*'[^']*'\);/" => "define('DB_USERNAME', '{$config_producao['DB_USERNAME']}');",
            "/define\('DB_PASSWORD',\s*'[^']*'\);/" => "define('DB_PASSWORD', '{$config_producao['DB_PASSWORD']}');",
            "/define\('DB_NAME',\s*'[^']*'\);/" => "define('DB_NAME', '{$config_producao['DB_NAME']}');"
        ]
    ],
    
    'pages/teste_gestao.php' => [
        'patterns' => [
            "/new PDO\(\"mysql:host=localhost;port=3307;dbname=sistema_frotas\",\s*\"root\",\s*\"\"\)/" => "new PDO(\"mysql:host={$config_producao['DB_SERVER']};port={$config_producao['DB_PORT']};dbname={$config_producao['DB_NAME']}\", \"{$config_producao['DB_USERNAME']}\", \"{$config_producao['DB_PASSWORD']}\")"
        ]
    ],
    
    'pages/gestao_interativa.php' => [
        'patterns' => [
            "/new PDO\(\"mysql:host=localhost;port=3307;dbname=sistema_frotas\",\s*\"root\",\s*\"\"\)/" => "new PDO(\"mysql:host={$config_producao['DB_SERVER']};port={$config_producao['DB_PORT']};dbname={$config_producao['DB_NAME']}\", \"{$config_producao['DB_USERNAME']}\", \"{$config_producao['DB_PASSWORD']}\")"
        ]
    ],
    
    'IA/recomendacao_pneus.php' => [
        'patterns' => [
            "/new PDO\(\"mysql:host=localhost;port=3307;dbname=sistema_frotas\",\s*\"root\",\s*\"\"\)/" => "new PDO(\"mysql:host={$config_producao['DB_SERVER']};port={$config_producao['DB_PORT']};dbname={$config_producao['DB_NAME']}\", \"{$config_producao['DB_USERNAME']}\", \"{$config_producao['DB_PASSWORD']}\")"
        ]
    ],
    
    'gestao_interativa/config/database.php' => [
        'patterns' => [
            "/'host'\s*=>\s*'[^']*'/" => "'host' => '{$config_producao['DB_SERVER']}'",
            "/'port'\s*=>\s*\d+/" => "'port' => {$config_producao['DB_PORT']}",
            "/'username'\s*=>\s*'[^']*'/" => "'username' => '{$config_producao['DB_USERNAME']}'",
            "/'password'\s*=>\s*'[^']*'/" => "'password' => '{$config_producao['DB_PASSWORD']}'",
            "/'database'\s*=>\s*'[^']*'/" => "'database' => '{$config_producao['DB_NAME']}'"
        ]
    ],
    
    'gestao_interativa/api/eixos_veiculos.php' => [
        'patterns' => [
            "/define\('DB_SERVER',\s*'[^']*'\)/" => "define('DB_SERVER', '{$config_producao['DB_SERVER']}:{$config_producao['DB_PORT']}')",
            "/define\('DB_USERNAME',\s*'[^']*'\)/" => "define('DB_USERNAME', '{$config_producao['DB_USERNAME']}')",
            "/define\('DB_PASSWORD',\s*'[^']*'\)/" => "define('DB_PASSWORD', '{$config_producao['DB_PASSWORD']}')",
            "/define\('DB_NAME',\s*'[^']*'\)/" => "define('DB_NAME', '{$config_producao['DB_NAME']}')"
        ]
    ],
    
    'gestao_interativa/api/posicoes_pneus.php' => [
        'patterns' => [
            "/define\('DB_SERVER',\s*'[^']*'\)/" => "define('DB_SERVER', '{$config_producao['DB_SERVER']}:{$config_producao['DB_PORT']}')",
            "/define\('DB_USERNAME',\s*'[^']*'\)/" => "define('DB_USERNAME', '{$config_producao['DB_USERNAME']}')",
            "/define\('DB_PASSWORD',\s*'[^']*'\)/" => "define('DB_PASSWORD', '{$config_producao['DB_PASSWORD']}')",
            "/define\('DB_NAME',\s*'[^']*'\)/" => "define('DB_NAME', '{$config_producao['DB_NAME']}')"
        ]
    ],
    
    'gestao_interativa/config/app.php' => [
        'patterns' => [
            "/'url'\s*=>\s*'[^']*'/" => "'url' => '{$config_producao['URL_BASE']}'"
        ]
    ],
    
    'gestao_interativa/config/config.php' => [
        'patterns' => [
            "/'url'\s*=>\s*'[^']*'/" => "'url' => '{$config_producao['URL_BASE']}'"
        ]
    ]
];

$sucessos = 0;
$erros = 0;

foreach ($arquivos as $arquivo => $config) {
    if (!file_exists($arquivo)) {
        echo "❌ Arquivo não encontrado: $arquivo\n";
        $erros++;
        continue;
    }
    
    echo "📝 Processando: $arquivo\n";
    
    $conteudo = file_get_contents($arquivo);
    $conteudo_original = $conteudo;
    
    foreach ($config['patterns'] as $pattern => $replacement) {
        $conteudo = preg_replace($pattern, $replacement, $conteudo);
    }
    
    if ($conteudo !== $conteudo_original) {
        // Fazer backup antes de alterar
        $backup_file = $arquivo . '.backup.' . date('Y-m-d-H-i-s');
        if (file_put_contents($backup_file, $conteudo_original)) {
            echo "   💾 Backup criado: $backup_file\n";
        }
        
        // Salvar alterações
        if (file_put_contents($arquivo, $conteudo)) {
            echo "   ✅ Configurado com sucesso\n";
            $sucessos++;
        } else {
            echo "   ❌ Erro ao salvar alterações\n";
            $erros++;
        }
    } else {
        echo "   ⚠️  Nenhuma alteração necessária\n";
    }
}

echo "\n=== RESUMO ===\n";
echo "✅ Arquivos configurados com sucesso: $sucessos\n";
echo "❌ Erros: $erros\n";

if ($erros == 0) {
    echo "\n🎉 Configuração concluída com sucesso!\n";
    echo "\n📋 PRÓXIMOS PASSOS:\n";
    echo "1. Teste a conexão com o banco de produção\n";
    echo "2. Execute o backup do banco atual\n";
    echo "3. Migre os dados para o banco de produção\n";
    echo "4. Teste todas as funcionalidades\n";
    echo "5. Configure o servidor web (Apache/Nginx)\n";
    echo "6. Configure SSL/HTTPS se necessário\n";
    echo "7. Configure backups automáticos\n";
} else {
    echo "\n⚠️  Alguns arquivos não puderam ser configurados.\n";
    echo "Verifique as permissões e tente novamente.\n";
}

echo "\n=== BACKUP DOS ARQUIVOS ===\n";
echo "Backups foram criados com extensão .backup.YYYY-MM-DD-HH-MM-SS\n";
echo "Para reverter, renomeie os arquivos de backup removendo a extensão.\n";

?> 