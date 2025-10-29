<?php
/**
 * SCRIPT DE DIAGNÓSTICO - SISTEMA SEGURO
 * Use este script para identificar problemas em produção
 */

// Desabilitar cache
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico - Sistema Seguro</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
        .badge-success { background: #28a745; color: white; }
        .badge-error { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: black; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico do Sistema - Produção</h1>
    
    <?php
    $erros = [];
    $avisos = [];
    $sucesso = [];
    
    // ===== TESTE 1: Versão PHP =====
    echo '<div class="card">';
    echo '<h2>1. Versão PHP</h2>';
    $phpVersion = phpversion();
    echo "<p>Versão atual: <strong>$phpVersion</strong></p>";
    
    if (version_compare($phpVersion, '7.4.0', '>=')) {
        echo '<p class="success">✅ Versão PHP compatível (7.4+)</p>';
        $sucesso[] = 'PHP versão OK';
    } else {
        echo '<p class="error">❌ PHP versão incompatível. Necessário 7.4 ou superior.</p>';
        $erros[] = 'PHP versão incompatível';
    }
    echo '</div>';
    
    // ===== TESTE 2: Extensões PHP =====
    echo '<div class="card">';
    echo '<h2>2. Extensões PHP Necessárias</h2>';
    
    $extensoes_necessarias = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
    foreach ($extensoes_necessarias as $ext) {
        if (extension_loaded($ext)) {
            echo "<p class='success'>✅ $ext: Instalado</p>";
        } else {
            echo "<p class='error'>❌ $ext: NÃO instalado</p>";
            $erros[] = "Extensão $ext não instalada";
        }
    }
    echo '</div>';
    
    // ===== TESTE 3: Arquivos Essenciais =====
    echo '<div class="card">';
    echo '<h2>3. Arquivos Essenciais</h2>';
    
    $arquivos_essenciais = [
        'config/database.php',
        'config/auth.php',
        '.htaccess',
        'index.php',
        'login.php'
    ];
    
    foreach ($arquivos_essenciais as $arquivo) {
        if (file_exists($arquivo)) {
            echo "<p class='success'>✅ $arquivo: Existe</p>";
            
            // Verificar permissões
            if (is_readable($arquivo)) {
                echo "<p class='info'>&nbsp;&nbsp;&nbsp;└─ Permissões: " . substr(sprintf('%o', fileperms($arquivo)), -4) . " (OK)</p>";
            } else {
                echo "<p class='error'>&nbsp;&nbsp;&nbsp;└─ Arquivo não é legível!</p>";
                $erros[] = "$arquivo não é legível";
            }
        } else {
            echo "<p class='error'>❌ $arquivo: NÃO encontrado</p>";
            $erros[] = "$arquivo não encontrado";
        }
    }
    echo '</div>';
    
    // ===== TESTE 4: Permissões de Diretórios =====
    echo '<div class="card">';
    echo '<h2>4. Permissões de Diretórios</h2>';
    
    $diretorios = ['logs', 'uploads', 'cache', 'sessions'];
    foreach ($diretorios as $dir) {
        if (is_dir($dir)) {
            echo "<p class='success'>✅ $dir/: Existe</p>";
            
            if (is_writable($dir)) {
                echo "<p class='success'>&nbsp;&nbsp;&nbsp;└─ Gravável: SIM</p>";
            } else {
                echo "<p class='error'>&nbsp;&nbsp;&nbsp;└─ Gravável: NÃO</p>";
                $erros[] = "$dir não é gravável";
                echo "<p class='warning'>&nbsp;&nbsp;&nbsp;└─ Execute: chmod 755 $dir/</p>";
            }
            
            echo "<p class='info'>&nbsp;&nbsp;&nbsp;└─ Permissões: " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";
        } else {
            echo "<p class='warning'>⚠️ $dir/: NÃO existe</p>";
            $avisos[] = "$dir não existe";
            echo "<p class='warning'>&nbsp;&nbsp;&nbsp;└─ Execute: mkdir $dir && chmod 755 $dir</p>";
        }
    }
    echo '</div>';
    
    // ===== TESTE 5: Conexão com Banco de Dados =====
    echo '<div class="card">';
    echo '<h2>5. Conexão com Banco de Dados</h2>';
    
    if (file_exists('config/database.php')) {
        try {
            // Temporariamente desabilitar display_errors
            $old_display = ini_get('display_errors');
            ini_set('display_errors', 0);
            
            require_once 'config/database.php';
            
            $db = getDB();
            
            if ($db) {
                echo '<p class="success">✅ Conexão estabelecida com sucesso!</p>';
                
                // Testar uma query simples
                try {
                    $stmt = $db->query("SELECT COUNT(*) as total FROM seguro_usuarios");
                    $result = $stmt->fetch();
                    echo "<p class='success'>✅ Query teste executada: {$result['total']} usuários no banco</p>";
                    $sucesso[] = 'Banco de dados OK';
                } catch (Exception $e) {
                    echo "<p class='error'>❌ Erro ao executar query teste: " . htmlspecialchars($e->getMessage()) . "</p>";
                    $erros[] = 'Erro na query: ' . $e->getMessage();
                }
                
            } else {
                echo '<p class="error">❌ Falha ao conectar ao banco de dados</p>';
                $erros[] = 'Falha na conexão com banco';
            }
            
            // Restaurar display_errors
            ini_set('display_errors', $old_display);
            
        } catch (Exception $e) {
            echo '<p class="error">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
            $erros[] = 'Erro de conexão: ' . $e->getMessage();
            
            echo '<p class="warning">Verifique:</p>';
            echo '<ul>';
            echo '<li>Host do banco de dados</li>';
            echo '<li>Nome do banco</li>';
            echo '<li>Usuário e senha</li>';
            echo '<li>Permissões do usuário</li>';
            echo '</ul>';
        }
    } else {
        echo '<p class="error">❌ Arquivo config/database.php não encontrado</p>';
        $erros[] = 'database.php não encontrado';
    }
    echo '</div>';
    
    // ===== TESTE 6: Configurações PHP =====
    echo '<div class="card">';
    echo '<h2>6. Configurações PHP</h2>';
    
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>Configuração</th><th>Valor</th><th>Status</th></tr>';
    
    $configs = [
        'display_errors' => ['atual' => ini_get('display_errors'), 'recomendado' => '0'],
        'error_reporting' => ['atual' => ini_get('error_reporting'), 'recomendado' => 'E_ALL'],
        'upload_max_filesize' => ['atual' => ini_get('upload_max_filesize'), 'recomendado' => '>=8M'],
        'post_max_size' => ['atual' => ini_get('post_max_size'), 'recomendado' => '>=8M'],
        'max_execution_time' => ['atual' => ini_get('max_execution_time'), 'recomendado' => '>=30'],
        'memory_limit' => ['atual' => ini_get('memory_limit'), 'recomendado' => '>=128M']
    ];
    
    foreach ($configs as $nome => $config) {
        echo "<tr>";
        echo "<td><strong>$nome</strong></td>";
        echo "<td>{$config['atual']}</td>";
        echo "<td>{$config['recomendado']}</td>";
        echo "</tr>";
    }
    echo '</table>';
    echo '</div>';
    
    // ===== TESTE 7: .htaccess =====
    echo '<div class="card">';
    echo '<h2>7. Arquivo .htaccess</h2>';
    
    if (file_exists('.htaccess')) {
        echo '<p class="success">✅ .htaccess existe</p>';
        
        $htaccess_content = file_get_contents('.htaccess');
        
        // Verificar mod_rewrite
        if (strpos($htaccess_content, 'RewriteEngine') !== false) {
            echo '<p class="success">✅ RewriteEngine encontrado</p>';
            
            // Verificar se mod_rewrite está habilitado
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                if (in_array('mod_rewrite', $modules)) {
                    echo '<p class="success">✅ mod_rewrite está habilitado</p>';
                } else {
                    echo '<p class="error">❌ mod_rewrite NÃO está habilitado</p>';
                    $erros[] = 'mod_rewrite não habilitado';
                    echo '<p class="warning">Execute: sudo a2enmod rewrite && sudo systemctl restart apache2</p>';
                }
            } else {
                echo '<p class="warning">⚠️ Não foi possível verificar mod_rewrite</p>';
            }
        }
        
        // Verificar PHP
        if (strpos($htaccess_content, 'php_') !== false) {
            echo '<p class="warning">⚠️ ATENÇÃO: .htaccess contém diretivas php_ que podem causar erro 500</p>';
            $avisos[] = 'Diretivas PHP no .htaccess';
            echo '<p class="warning">Se estiver usando PHP-FPM, remova todas as linhas começando com php_</p>';
        }
        
    } else {
        echo '<p class="warning">⚠️ .htaccess não encontrado</p>';
        $avisos[] = '.htaccess não encontrado';
    }
    echo '</div>';
    
    // ===== TESTE 8: Logs de Erro =====
    echo '<div class="card">';
    echo '<h2>8. Logs de Erro</h2>';
    
    $log_file = 'logs/php_errors.log';
    if (file_exists($log_file) && is_readable($log_file)) {
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $last_lines = array_slice($lines, -20);
        
        if (!empty(trim($log_content))) {
            echo '<p class="warning">⚠️ Existem erros no log:</p>';
            echo '<pre>' . htmlspecialchars(implode("\n", $last_lines)) . '</pre>';
        } else {
            echo '<p class="success">✅ Nenhum erro recente no log</p>';
        }
    } else {
        echo '<p class="info">ℹ️ Log de erros não encontrado ou não legível</p>';
    }
    echo '</div>';
    
    // ===== RESUMO FINAL =====
    echo '<div class="card" style="background: #f8f9fa;">';
    echo '<h2>📊 Resumo Final</h2>';
    
    echo '<h3>✅ Sucessos (' . count($sucesso) . ')</h3>';
    if (count($sucesso) > 0) {
        echo '<ul>';
        foreach ($sucesso as $s) {
            echo "<li class='success'>$s</li>";
        }
        echo '</ul>';
    }
    
    echo '<h3>⚠️ Avisos (' . count($avisos) . ')</h3>';
    if (count($avisos) > 0) {
        echo '<ul>';
        foreach ($avisos as $a) {
            echo "<li class='warning'>$a</li>";
        }
        echo '</ul>';
    }
    
    echo '<h3>❌ Erros Críticos (' . count($erros) . ')</h3>';
    if (count($erros) > 0) {
        echo '<ul>';
        foreach ($erros as $e) {
            echo "<li class='error'>$e</li>";
        }
        echo '</ul>';
    } else {
        echo '<p class="success">Nenhum erro crítico encontrado!</p>';
    }
    
    echo '</div>';
    
    // ===== INFORMAÇÕES DO SERVIDOR =====
    echo '<div class="card">';
    echo '<h2>ℹ️ Informações do Servidor</h2>';
    echo '<ul>';
    echo '<li><strong>PHP Version:</strong> ' . phpversion() . '</li>';
    echo '<li><strong>Server Software:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</li>';
    echo '<li><strong>Document Root:</strong> ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '</li>';
    echo '<li><strong>Script Filename:</strong> ' . __FILE__ . '</li>';
    echo '<li><strong>Current Directory:</strong> ' . getcwd() . '</li>';
    echo '<li><strong>OS:</strong> ' . PHP_OS . '</li>';
    echo '</ul>';
    echo '</div>';
    
    ?>
    
    <div class="card" style="background: #e3f2fd;">
        <h2>🔧 Próximos Passos</h2>
        
        <?php if (count($erros) > 0): ?>
            <h3>Correções Necessárias:</h3>
            <ol>
                <li>Verifique os erros críticos acima</li>
                <li>Corrija as permissões dos diretórios (chmod 755)</li>
                <li>Verifique as credenciais do banco de dados</li>
                <li>Se .htaccess tiver diretivas php_, remova-as</li>
                <li>Consulte os logs do Apache: <code>tail -f /var/log/apache2/error.log</code></li>
            </ol>
        <?php else: ?>
            <p class="success">✅ Sistema parece estar OK!</p>
            <p>Se ainda estiver com erro 500, verifique:</p>
            <ol>
                <li>Logs do Apache: <code>/var/log/apache2/error.log</code></li>
                <li>Sintaxe dos arquivos PHP</li>
                <li>Se .htaccess não tem diretivas incompatíveis</li>
            </ol>
        <?php endif; ?>
    </div>
    
    <div class="card" style="background: #fff3cd;">
        <h2>⚠️ IMPORTANTE: Segurança</h2>
        <p><strong>Após corrigir os problemas, REMOVA este arquivo (diagnostico.php) do servidor!</strong></p>
        <p>Este arquivo expõe informações sensíveis do sistema.</p>
    </div>
    
    <p style="text-align: center; color: #999; margin-top: 30px;">
        Sistema Seguro - Diagnóstico v1.0
    </p>
</body>
</html>

