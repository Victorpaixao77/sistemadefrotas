<?php
/**
 * ADICIONAR CAMPO tem_acesso_seguro
 * Corrige o erro: Column not found 'tem_acesso_seguro'
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Campo - tem_acesso_seguro</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        h1 { color: #333; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #5568d3; }
    </style>
</head>
<body>
    <h1>🔧 Adicionar Campo tem_acesso_seguro</h1>
    
    <div class="card">
        <h2>Sobre este Script:</h2>
        <p>Este script adiciona o campo <strong>tem_acesso_seguro</strong> na tabela <strong>empresa_adm</strong>.</p>
        <p>Este campo é necessário para o sistema identificar quais empresas têm acesso ao Sistema Seguro.</p>
    </div>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = getDB();
        
        echo '<div class="card">';
        echo '<h2>Executando correção...</h2>';
        
        try {
            // Verificar se a coluna já existe
            $stmt = $db->query("SHOW COLUMNS FROM empresa_adm LIKE 'tem_acesso_seguro'");
            $coluna_existe = $stmt->fetch();
            
            if ($coluna_existe) {
                echo '<p class="warning">⚠️ Campo tem_acesso_seguro já existe!</p>';
            } else {
                // Adicionar coluna
                $db->exec("
                    ALTER TABLE empresa_adm 
                    ADD COLUMN tem_acesso_seguro ENUM('sim', 'nao') DEFAULT 'nao' 
                    AFTER plano
                ");
                
                echo '<p class="success">✅ Campo tem_acesso_seguro adicionado com sucesso!</p>';
            }
            
            // Atualizar empresas Premium/Enterprise
            $stmt = $db->exec("
                UPDATE empresa_adm 
                SET tem_acesso_seguro = 'sim' 
                WHERE plano IN ('premium', 'enterprise')
            ");
            
            echo '<p class="success">✅ Empresas Premium/Enterprise atualizadas: ' . $stmt . ' registros</p>';
            
            // Listar empresas
            echo '<h3>Empresas com acesso:</h3>';
            $stmt = $db->query("
                SELECT id, razao_social, plano, tem_acesso_seguro 
                FROM empresa_adm 
                ORDER BY id
            ");
            $empresas = $stmt->fetchAll();
            
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
            echo '<tr><th>ID</th><th>Razão Social</th><th>Plano</th><th>Acesso Seguro</th></tr>';
            
            foreach ($empresas as $emp) {
                $classe = $emp['tem_acesso_seguro'] === 'sim' ? 'success' : '';
                echo "<tr class='$classe'>";
                echo "<td>{$emp['id']}</td>";
                echo "<td>{$emp['razao_social']}</td>";
                echo "<td>{$emp['plano']}</td>";
                echo "<td><strong>{$emp['tem_acesso_seguro']}</strong></td>";
                echo "</tr>";
            }
            
            echo '</table>';
            
            echo '<hr>';
            echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px;">';
            echo '<h3 style="color: #155724;">✅ CORREÇÃO CONCLUÍDA!</h3>';
            echo '<p>Agora você pode fazer login no sistema:</p>';
            echo '<p><strong><a href="login.php" style="font-size: 18px;">Acessar Login</a></strong></p>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<p class="error">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
            
            // Se o erro for que a coluna já existe, não é um problema
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo '<p class="warning">O campo já existe. Tente fazer login novamente.</p>';
                echo '<p><a href="login.php"><button>Ir para Login</button></a></p>';
            }
        }
        
        echo '</div>';
        
    } else {
        // Mostrar formulário
        ?>
        
        <div class="card">
            <h2>⚠️ Atenção:</h2>
            <p>Este script irá modificar a estrutura do banco de dados.</p>
            <p><strong>O que será feito:</strong></p>
            <ol>
                <li>Adicionar campo <code>tem_acesso_seguro</code> na tabela <code>empresa_adm</code></li>
                <li>Atualizar todas as empresas Premium/Enterprise com acesso automático</li>
                <li>Listar todas as empresas e seus acessos</li>
            </ol>
            
            <p class="warning"><strong>Importante:</strong> Faça backup do banco de dados antes de continuar.</p>
        </div>
        
        <div class="card">
            <form method="POST">
                <button type="submit">✅ Executar Correção</button>
            </form>
        </div>
        
        <div class="card">
            <h3>Correção Manual (via SQL):</h3>
            <p>Se preferir, execute este SQL diretamente no phpMyAdmin:</p>
            <pre>-- Adicionar campo
ALTER TABLE empresa_adm 
ADD COLUMN tem_acesso_seguro ENUM('sim', 'nao') DEFAULT 'nao' 
AFTER plano;

-- Atualizar empresas Premium
UPDATE empresa_adm 
SET tem_acesso_seguro = 'sim' 
WHERE plano IN ('premium', 'enterprise');

-- Verificar
SELECT id, razao_social, plano, tem_acesso_seguro 
FROM empresa_adm;</pre>
        </div>
        
        <?php
    }
    ?>
    
    <div class="card" style="background: #fff3cd;">
        <h3>📋 Outros Problemas Identificados:</h3>
        <ul>
            <li><strong>Permissões:</strong> logs/ e cache/ precisam de permissão de escrita</li>
            <li><strong>Diretórios:</strong> uploads/ e sessions/ precisam ser criados</li>
            <li><strong>API Atividades:</strong> Erro na coluna 'l.tabela' (não afeta login)</li>
        </ul>
        
        <h4>Via SSH:</h4>
        <pre>cd /var/www/html/sistema-frotas/sistema-seguro/
sudo mkdir -p logs uploads cache sessions
sudo chmod 755 logs uploads cache sessions
sudo chown -R www-data:www-data logs uploads cache sessions</pre>
    </div>
    
    <p style="text-align: center; color: #999; margin-top: 30px;">
        Sistema Seguro - Correção Automática v1.0<br>
        <a href="diagnostico.php">← Voltar para Diagnóstico</a>
    </p>
</body>
</html>

