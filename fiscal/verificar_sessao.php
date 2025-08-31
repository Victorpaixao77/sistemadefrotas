<?php
/**
 * 🔍 VERIFICADOR DE SESSÃO
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🔍 Verificador de Sessão</h1>";

// Configurar sessão
configure_session();
session_start();

echo "<h2>📊 Informações da Sessão</h2>";
echo "<p><strong>ID da Sessão:</strong> " . session_id() . "</p>";
echo "<p><strong>Status da Sessão:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Ativa' : 'Inativa') . "</p>";

echo "<h3>🔑 Variáveis de Sessão:</h3>";
if (empty($_SESSION)) {
    echo "<p>❌ Nenhuma variável de sessão encontrada</p>";
} else {
    echo "<ul>";
    foreach ($_SESSION as $key => $value) {
        if (is_array($value)) {
            echo "<li><strong>$key:</strong> [Array]</li>";
        } else {
            echo "<li><strong>$key:</strong> " . htmlspecialchars($value) . "</li>";
        }
    }
    echo "</ul>";
}

echo "<h2>🏢 Verificação de Empresa</h2>";
if (isset($_SESSION['empresa_id'])) {
    echo "<p>✅ empresa_id encontrado na sessão: {$_SESSION['empresa_id']}</p>";
    
    try {
        $conn = getConnection();
        
        // Verificar se a empresa existe
        $stmt = $conn->prepare("SELECT id, razao_social, nome_fantasia FROM empresa_clientes WHERE id = ?");
        $stmt->execute([$_SESSION['empresa_id']]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            echo "<p>✅ Empresa encontrada no banco:</p>";
            echo "<ul>";
            echo "<li><strong>ID:</strong> {$empresa['id']}</li>";
            echo "<li><strong>Razão Social:</strong> {$empresa['razao_social']}</li>";
            echo "<li><strong>Nome Fantasia:</strong> {$empresa['nome_fantasia']}</li>";
            echo "</ul>";
        } else {
            echo "<p>❌ Empresa não encontrada no banco para o ID: {$_SESSION['empresa_id']}</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao conectar com banco: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p>❌ empresa_id não encontrado na sessão</p>";
    
    // Tentar criar uma sessão de teste
    echo "<h3>🧪 Criando Sessão de Teste</h3>";
    
    // Verificar se existe alguma empresa no banco
    try {
        $conn = getConnection();
        $stmt = $conn->query("SELECT id, razao_social FROM empresa_clientes LIMIT 1");
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $_SESSION['empresa_id'] = $empresa['id'];
            $_SESSION['empresa_nome'] = $empresa['razao_social'];
            echo "<p>✅ Sessão de teste criada com empresa_id: {$empresa['id']}</p>";
            echo "<p>✅ empresa_nome: {$empresa['razao_social']}</p>";
        } else {
            echo "<p>❌ Nenhuma empresa encontrada no banco</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro ao conectar com banco: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>🔧 Ações</h2>";
echo "<p><a href='verificar_sessao.php'>🔄 Recarregar</a></p>";
echo "<p><a href='pages/nfe.php'>📄 Ir para NFe</a></p>";
echo "<p><a href='teste_nfe.php'>🧪 Testar NFe</a></p>";

// Se não houver empresa_id, mostrar formulário para criar
if (!isset($_SESSION['empresa_id'])) {
    echo "<h3>➕ Criar Empresa de Teste</h3>";
    echo "<form method='post'>";
    echo "<p><label>Razão Social: <input type='text' name='razao_social' value='Empresa Teste LTDA' required></label></p>";
    echo "<p><label>CNPJ: <input type='text' name='cnpj' value='00.000.000/0001-00' required></label></p>";
    echo "<p><input type='submit' name='criar_empresa' value='Criar Empresa'></p>";
    echo "</form>";
    
    if (isset($_POST['criar_empresa'])) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("INSERT INTO empresa_clientes (empresa_adm_id, razao_social, cnpj, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([1, $_POST['razao_social'], $_POST['cnpj'], 'ativo']);
            
            $empresa_id = $conn->lastInsertId();
            $_SESSION['empresa_id'] = $empresa_id;
            $_SESSION['empresa_nome'] = $_POST['razao_social'];
            
            echo "<p>✅ Empresa criada com sucesso! ID: $empresa_id</p>";
            echo "<script>location.reload();</script>";
            
        } catch (Exception $e) {
            echo "<p>❌ Erro ao criar empresa: " . $e->getMessage() . "</p>";
        }
    }
}
?>
