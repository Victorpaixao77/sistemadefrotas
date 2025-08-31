<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$page_title = "Teste SEFAZ Simples";
include '../includes/header.php';
?>

<div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">
                                <i class="fas fa-check-circle text-success"></i>
                                Teste SEFAZ Simples
                            </h4>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-certificate"></i>
                                    Teste de Validação
                                </h5>
                            </div>
                            <div class="card-body">
                                <button type="button" class="btn btn-primary btn-lg mb-3" onclick="testarAPI()">
                                    <i class="fas fa-check"></i>
                                    Testar API SEFAZ
                                </button>
                                <div id="resultado-teste"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testarAPI() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultado = document.getElementById('resultado-teste');
            
            if (data.success) {
                resultado.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>API Funcionando!</strong><br>
                        <strong>Certificado:</strong> ${data.certificado.detalhes.status}<br>
                        <strong>Ambiente:</strong> ${data.conexao_sefaz.ambiente}<br>
                        <strong>Status SEFAZ:</strong> ${data.conexao_sefaz.status_geral}
                    </div>
                `;
            } else {
                resultado.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i>
                        <strong>Erro na API:</strong><br>
                        ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            const resultado = document.getElementById('resultado-teste');
            resultado.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i>
                    <strong>Erro de Conexão:</strong><br>
                    ${error.message}
                </div>
            `;
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}
</script>

<?php include '../includes/footer.php'; ?>
