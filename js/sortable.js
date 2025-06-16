/**
 * Drag-and-drop functionality for dashboard cards
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sortable dashboard grid
    initSortableDashboard();
});

/**
 * Initialize sortable dashboard grid with drag-and-drop
 */
function initSortableDashboard() {
    const dashboardGrid = document.getElementById('dashboardGrid');
    
    if (!dashboardGrid) return;
    
    // Create Sortable instance for dashboard grid
    new Sortable(dashboardGrid, {
        animation: 150,
        ghostClass: 'card-ghost',
        chosenClass: 'card-chosen',
        dragClass: 'card-drag', 
        handle: '.card-header', // Permite arrastar apenas pelo cabeçalho do card
        forceFallback: true, // Forçar o uso do fallback para melhor compatibilidade
        fallbackClass: 'sortable-fallback', // Classe para o elemento de fallback
        fallbackOnBody: true, // Anexar elemento fantasma ao corpo para melhor visualização
        swapThreshold: 0.65, // Ajuste para melhorar a detecção de troca
        onEnd: function(evt) {
            // Save new layout after drag ends
            saveCurrentLayout();
        }
    });
    
    // Load saved layout
    loadSavedLayout();
}

/**
 * Load saved dashboard layout from localStorage
 */
function loadSavedLayout() {
    const dashboardGrid = document.getElementById('dashboardGrid');
    if (!dashboardGrid) return;
    
    try {
        // Try to get layout from localStorage
        const savedLayout = localStorage.getItem('dashboardLayout');
        
        if (savedLayout) {
            const layout = JSON.parse(savedLayout);
            
            // Sort cards based on saved order
            const cards = Array.from(dashboardGrid.querySelectorAll('.dashboard-card'));
            
            // Create a sorted array of cards
            const sortedCards = [];
            
            // First add cards that exist in the saved layout
            layout.forEach(item => {
                const card = cards.find(c => c.getAttribute('data-card-id') === item.id);
                if (card) {
                    sortedCards.push(card);
                }
            });
            
            // Then add any cards that don't exist in the saved layout
            cards.forEach(card => {
                const cardId = card.getAttribute('data-card-id');
                if (!layout.some(item => item.id === cardId)) {
                    sortedCards.push(card);
                }
            });
            
            // Clear dashboard grid
            dashboardGrid.innerHTML = '';
            
            // Add cards in the correct order
            sortedCards.forEach(card => {
                dashboardGrid.appendChild(card);
            });
        }
        
        // Check for compact layout preference
        const isCompact = localStorage.getItem('dashboardCompactLayout') === 'true';
        if (isCompact) {
            dashboardGrid.classList.add('compact-layout');
        }
        
    } catch (error) {
        console.error('Error loading saved layout:', error);
    }
}

/**
 * Save current dashboard layout
 */
function saveCurrentLayout() {
    const dashboardGrid = document.getElementById('dashboardGrid');
    if (!dashboardGrid) return;
    
    // Get all cards and their IDs
    const cards = dashboardGrid.querySelectorAll('.dashboard-card');
    const layout = Array.from(cards).map((card, index) => {
        return {
            id: card.getAttribute('data-card-id'),
            order: index
        };
    });
    
    // Save to localStorage
    localStorage.setItem('dashboardLayout', JSON.stringify(layout));
    
    // Also send to server for persistent storage (if available)
    if (typeof fetch !== 'undefined') {
        fetch('api/save_layout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ layout })
        })
        .catch(error => {
            console.error('Error saving layout to server:', error);
        });
    }
}
