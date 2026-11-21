/**
 * Verifica pendências de rotas/abastecimentos e atualiza badges do header.
 */
function verificarPendencias() {
    var url = typeof sfAppUrl === 'function'
        ? sfAppUrl('notificacoes/sync_all.php')
        : '../notificacoes/sync_all.php';
    fetch(url, { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success && typeof window.atualizarContadores === 'function') {
                window.atualizarContadores();
            }
        })
        // Best-effort: fetch abortado (navegação) ou falha de rede → re-tenta no próximo ciclo.
        .catch(function (error) { console.debug('verificar pendências adiado:', error && error.message); });
}

setInterval(verificarPendencias, 5 * 60 * 1000);
document.addEventListener('DOMContentLoaded', verificarPendencias);
