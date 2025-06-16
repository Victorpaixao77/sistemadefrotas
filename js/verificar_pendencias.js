// Função para verificar pendências
function verificarPendencias() {
    fetch('../notificacoes/verificar_pendencias.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar contadores de notificações
                atualizarContadores();
            }
        })
        .catch(error => console.error('Erro ao verificar pendências:', error));
}

// Verificar pendências a cada 5 minutos
setInterval(verificarPendencias, 5 * 60 * 1000);

// Verificar pendências ao carregar a página
document.addEventListener('DOMContentLoaded', verificarPendencias); 