// ============================================
// SISTEMA DE TEMAS CLARO/ESCURO
// ============================================

console.log('🎨 Sistema de Temas carregado');

// Variável global para armazenar o tema atual
let temaAtual = 'claro';
let coresPersonalizadas = {
    primaria: '#667eea',
    secundaria: '#764ba2',
    destaque: '#28a745'
};

// Carregar tema ao iniciar
document.addEventListener('DOMContentLoaded', function() {
    carregarTema();
    criarToggleTema();
});

// Função para carregar o tema do usuário
async function carregarTema() {
    try {
        const response = await fetch('api/obter_tema.php');
        const data = await response.json();
        
        if (data.sucesso) {
            coresPersonalizadas = data.cores;
            
            // Aplicar cores personalizadas
            aplicarCoresPersonalizadas();
            
            // Detectar tema
            let temaFinal = data.tema;
            
            // Se o tema for 'auto', detectar preferência do sistema
            if (data.tema === 'auto') {
                temaFinal = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro';
                console.log('🌓 Modo automático detectado:', temaFinal);
            }
            
            // Aplicar tema
            aplicarTema(temaFinal);
            
            console.log('✅ Tema carregado:', temaFinal);
            console.log('🎨 Cores:', coresPersonalizadas);
        }
    } catch (error) {
        console.error('❌ Erro ao carregar tema:', error);
        // Aplicar tema padrão em caso de erro
        aplicarTema('claro');
    }
}

// Função para aplicar o tema
function aplicarTema(tema) {
    temaAtual = tema;
    
    // Aplicar atributo data-theme no root
    document.documentElement.setAttribute('data-theme', tema);
    
    // Salvar no localStorage para aplicação instantânea
    localStorage.setItem('tema_preferido', tema);
    
    // Atualizar ícone do toggle
    atualizarIconeToggle();
    
    console.log('🎨 Tema aplicado:', tema);
}

// Função para escurecer uma cor hex
function escurecerCor(cor, percentual = 0.3) {
    // Remove o #
    cor = cor.replace('#', '');
    
    // Converte para RGB
    let r = parseInt(cor.substring(0, 2), 16);
    let g = parseInt(cor.substring(2, 4), 16);
    let b = parseInt(cor.substring(4, 6), 16);
    
    // Escurece
    r = Math.floor(r * (1 - percentual));
    g = Math.floor(g * (1 - percentual));
    b = Math.floor(b * (1 - percentual));
    
    // Converte de volta para hex
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

// Função para saturar uma cor (deixar mais vibrante)
function saturarCor(cor, percentual = 0.2) {
    // Remove o #
    cor = cor.replace('#', '');
    
    // Converte para RGB
    let r = parseInt(cor.substring(0, 2), 16);
    let g = parseInt(cor.substring(2, 4), 16);
    let b = parseInt(cor.substring(4, 6), 16);
    
    // Encontra o valor médio
    const media = (r + g + b) / 3;
    
    // Aumenta a diferença de cada canal em relação à média
    r = Math.min(255, Math.floor(r + (r - media) * percentual));
    g = Math.min(255, Math.floor(g + (g - media) * percentual));
    b = Math.min(255, Math.floor(b + (b - media) * percentual));
    
    // Converte de volta para hex
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

// Função para aplicar cores personalizadas
function aplicarCoresPersonalizadas() {
    const root = document.documentElement;
    
    // Aplicar cores CSS customizadas
    root.style.setProperty('--cor-primaria-custom', coresPersonalizadas.primaria);
    root.style.setProperty('--cor-secundaria-custom', coresPersonalizadas.secundaria);
    root.style.setProperty('--cor-destaque-custom', coresPersonalizadas.destaque);
    
    // Criar versões mais escuras e saturadas para o sidebar
    const primEscura = escurecerCor(saturarCor(coresPersonalizadas.primaria, 0.3), 0.2);
    const secEscura = escurecerCor(saturarCor(coresPersonalizadas.secundaria, 0.3), 0.2);
    
    console.log('🎨 Cores do Sidebar:');
    console.log('  Original Primária:', coresPersonalizadas.primaria);
    console.log('  Sidebar Primária:', primEscura);
    console.log('  Original Secundária:', coresPersonalizadas.secundaria);
    console.log('  Sidebar Secundária:', secEscura);
    
    // Atualizar gradient do sidebar se existir (usando cores mais escuras)
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.style.background = `linear-gradient(135deg, ${primEscura} 0%, ${secEscura} 100%)`;
    }
    
    // Atualizar botão de toggle (cor escura)
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.style.background = primEscura;
    }
    
    // Atualizar cards gradiente (cores originais, mais claras)
    const statCards = document.querySelectorAll('.stat-card, .btn-gradient');
    statCards.forEach(card => {
        card.style.background = `linear-gradient(135deg, ${coresPersonalizadas.primaria} 0%, ${coresPersonalizadas.secundaria} 100%)`;
    });
}

// Função para criar o botão de toggle de tema
function criarToggleTema() {
    // Verificar se já existe
    if (document.getElementById('themeToggle')) {
        return;
    }
    
    const toggle = document.createElement('button');
    toggle.id = 'themeToggle';
    toggle.className = 'theme-toggle';
    toggle.setAttribute('title', 'Alternar tema');
    toggle.innerHTML = '<i class="fas fa-moon"></i>';
    toggle.onclick = toggleTema;
    
    document.body.appendChild(toggle);
}

// Função para alternar entre temas
async function toggleTema() {
    const novoTema = temaAtual === 'claro' ? 'escuro' : 'claro';
    
    try {
        // Salvar no servidor
        const response = await fetch('api/salvar_tema.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tema: novoTema
            })
        });
        
        const data = await response.json();
        
        if (data.sucesso) {
            // Aplicar tema localmente
            aplicarTema(novoTema);
            
            // Feedback visual
            mostrarNotificacaoTema(novoTema);
        } else {
            console.error('❌ Erro ao salvar tema:', data.mensagem);
        }
    } catch (error) {
        console.error('❌ Erro ao alternar tema:', error);
        // Aplicar localmente mesmo se houver erro ao salvar
        aplicarTema(novoTema);
    }
}

// Função para atualizar o ícone do toggle
function atualizarIconeToggle() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    
    if (temaAtual === 'escuro') {
        toggle.innerHTML = '<i class="fas fa-sun"></i>';
        toggle.setAttribute('title', 'Modo Claro');
    } else {
        toggle.innerHTML = '<i class="fas fa-moon"></i>';
        toggle.setAttribute('title', 'Modo Escuro');
    }
}

// Função para mostrar notificação de mudança de tema
function mostrarNotificacaoTema(tema) {
    // Criar notificação temporária
    const notificacao = document.createElement('div');
    notificacao.className = 'alert alert-success';
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 250px;
        animation: slideIn 0.3s ease;
    `;
    
    const icone = tema === 'escuro' ? '🌙' : '☀️';
    const texto = tema === 'escuro' ? 'Modo Escuro' : 'Modo Claro';
    
    notificacao.innerHTML = `
        <strong>${icone} ${texto} Ativado!</strong>
    `;
    
    document.body.appendChild(notificacao);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notificacao.remove(), 300);
    }, 3000);
}

// Detectar mudanças na preferência do sistema (para modo auto)
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        // Só aplicar se o usuário estiver em modo auto
        if (localStorage.getItem('tema_modo') === 'auto') {
            const novoTema = e.matches ? 'escuro' : 'claro';
            aplicarTema(novoTema);
            console.log('🌓 Preferência do sistema mudou:', novoTema);
        }
    });
}

// Aplicar tema instantaneamente antes do carregamento completo
// (evita "flash" de tema incorreto)
(function() {
    const temaSalvo = localStorage.getItem('tema_preferido');
    if (temaSalvo) {
        document.documentElement.setAttribute('data-theme', temaSalvo);
    }
})();

// Adicionar CSS para animações
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

console.log('✅ Sistema de Temas inicializado');

// Exportar funções para uso global
window.toggleTema = toggleTema;
window.aplicarTema = aplicarTema;
window.carregarTema = carregarTema;

