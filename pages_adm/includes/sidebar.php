<?php
// Obter o nome da página atual
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Sistema de Frotas</h2>
        <p>Painel Administrativo</p>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $current_page == 'empresas.php' ? 'active' : ''; ?>">
                <a href="empresas.php">
                    <i class="fas fa-building"></i>
                    <span>Empresas</span>
                </a>
            </li>
            <li class="<?php echo $current_page == 'planos.php' ? 'active' : ''; ?>">
                <a href="planos.php">
                    <i class="fas fa-tags"></i>
                    <span>Planos</span>
                </a>
            </li>
            <li class="<?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
                <a href="usuarios.php">
                    <i class="fas fa-users"></i>
                    <span>Usuários</span>
                </a>
            </li>
            <li class="<?php echo $current_page == 'log_acessos.php' ? 'active' : ''; ?>">
                <a href="log_acessos.php">
                    <i class="fas fa-history"></i>
                    <span>Log de Acessos</span>
                </a>
            </li>
            <li class="<?php echo $current_page == 'posicao_financeira.php' ? 'active' : ''; ?>">
                <a href="posicao_financeira.php">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Posição Financeira</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </li>
        </ul>
    </nav>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background: #2c3e50;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    padding: 20px 0;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 20px;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 1.5em;
    color: #fff;
}

.sidebar-header p {
    margin: 5px 0 0;
    font-size: 0.9em;
    color: rgba(255,255,255,0.7);
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-nav li {
    margin: 5px 0;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s ease;
}

.sidebar-nav a:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.sidebar-nav li.active a {
    background: #3498db;
    color: #fff;
}

.sidebar-nav i {
    width: 20px;
    margin-right: 10px;
    text-align: center;
}

@media (max-width: 768px) {
    .sidebar {
        width: 60px;
        padding: 20px 0;
    }
    
    .sidebar-header {
        padding: 0 10px 20px;
    }
    
    .sidebar-header h2,
    .sidebar-header p,
    .sidebar-nav span {
        display: none;
    }
    
    .sidebar-nav a {
        padding: 12px;
        justify-content: center;
    }
    
    .sidebar-nav i {
        margin: 0;
        font-size: 1.2em;
    }
}
</style> 