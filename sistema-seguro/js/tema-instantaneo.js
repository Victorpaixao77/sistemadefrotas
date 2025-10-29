// ============================================
// APLICAÇÃO INSTANTÂNEA DE TEMA
// Este script DEVE ser carregado ANTES de qualquer CSS
// ============================================

(function() {
    // Buscar tema salvo no localStorage
    const temaSalvo = localStorage.getItem('tema_preferido');
    
    if (temaSalvo) {
        // Aplicar IMEDIATAMENTE no HTML
        document.documentElement.setAttribute('data-theme', temaSalvo);
        console.log('⚡ Tema aplicado instantaneamente:', temaSalvo);
    } else {
        // Se não houver tema salvo, usar claro por padrão
        document.documentElement.setAttribute('data-theme', 'claro');
        console.log('⚡ Tema padrão aplicado: claro');
    }
})();

