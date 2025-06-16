// Funções para manipulação de modais
function openModal(modalId) {
    console.log('Abrindo modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('Modal não encontrado:', modalId);
    }
}

function closeModal(modalId) {
    console.log('Fechando modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    } else {
        console.error('Modal não encontrado:', modalId);
    }
}

// Fechar modal quando clicar no X
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado, inicializando eventos...');
    
    const closeButtons = document.getElementsByClassName('close-modal');
    console.log('Botões de fechar encontrados:', closeButtons.length);
    
    for (let button of closeButtons) {
        button.onclick = function() {
            console.log('Botão de fechar clicado');
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    }

    // Fechar modal quando clicar fora dele
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            console.log('Clicou fora do modal, fechando...');
            event.target.style.display = 'none';
        }
    }

    // Botões de filtro e ajuda
    const filterBtn = document.getElementById('filterBtn');
    const helpBtn = document.getElementById('helpBtn');

    console.log('Botão de filtro encontrado:', !!filterBtn);
    console.log('Botão de ajuda encontrado:', !!helpBtn);

    if (filterBtn) {
        filterBtn.onclick = function() {
            console.log('Botão de filtro clicado');
            openModal('filterModal');
        }
    }

    if (helpBtn) {
        helpBtn.onclick = function() {
            console.log('Botão de ajuda clicado');
            openModal('helpModal');
        }
    }

    // Função para visualizar pneu
    const viewButtons = document.getElementsByClassName('view-btn');
    console.log('Botões de visualizar encontrados:', viewButtons.length);
    
    for (let button of viewButtons) {
        button.onclick = function() {
            const pneuId = this.getAttribute('data-id');
            console.log('Visualizando pneu:', pneuId);
            window.location.href = `pneus.php?id=${pneuId}`;
        }
    }

    // Função para editar pneu
    const editButtons = document.getElementsByClassName('edit-btn');
    console.log('Botões de editar encontrados:', editButtons.length);
    
    for (let button of editButtons) {
        button.onclick = function() {
            const pneuId = this.getAttribute('data-id');
            console.log('Editando pneu:', pneuId);
            window.location.href = `pneus.php?id=${pneuId}&edit=true`;
        }
    }
});

// Função para atualizar a tabela com dados filtrados
function updateTable(pneus) {
    console.log('Atualizando tabela com pneus:', pneus);
    
    const tbody = document.querySelector('.data-table tbody');
    if (!tbody) {
        console.error('Elemento tbody não encontrado');
        return;
    }

    tbody.innerHTML = '';
    
    pneus.forEach(pneu => {
        console.log('Adicionando pneu à tabela:', pneu);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${pneu.numero_serie || ''}</td>
            <td>${pneu.marca || ''}</td>
            <td>${pneu.modelo || ''}</td>
            <td>${pneu.medida || ''}</td>
            <td>
                <span class="status-badge status-${(pneu.status_nome || '').toLowerCase()}">
                    ${pneu.status_nome || ''}
                </span>
            </td>
            <td>
                <span class="status-badge ${pneu.disponivel ? 'status-success' : 'status-warning'}">
                    ${pneu.disponivel ? 'Sim' : 'Não'}
                </span>
            </td>
            <td>${pneu.data_entrada ? new Date(pneu.data_entrada).toLocaleDateString() : ''}</td>
            <td class="actions">
                <button class="btn-icon view-btn" data-id="${pneu.pneu_id}" title="Ver detalhes">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-icon edit-btn" data-id="${pneu.pneu_id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Reatribuir eventos aos novos botões
    const viewButtons = tbody.getElementsByClassName('view-btn');
    const editButtons = tbody.getElementsByClassName('edit-btn');

    console.log('Novos botões de visualizar:', viewButtons.length);
    console.log('Novos botões de editar:', editButtons.length);

    for (let button of viewButtons) {
        button.onclick = function() {
            const pneuId = this.getAttribute('data-id');
            console.log('Visualizando pneu (novo):', pneuId);
            window.location.href = `pneus.php?id=${pneuId}`;
        }
    }

    for (let button of editButtons) {
        button.onclick = function() {
            const pneuId = this.getAttribute('data-id');
            console.log('Editando pneu (novo):', pneuId);
            window.location.href = `pneus.php?id=${pneuId}&edit=true`;
        }
    }
}

// Função para mudar de página
function changePage(page) {
    console.log('Mudando para página:', page);
    window.location.href = `estoque_pneus.php?page=${page}`;
    return false;
} 