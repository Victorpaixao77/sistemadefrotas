/**
 * Sidebar functionality management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar state
    initializeSidebar();
    
    // Setup sidebar toggle
    setupSidebarToggle();
    
    // Setup mobile menu
    setupMobileMenu();
    
    // Setup dropdown menus
    setupDropdowns();
});

/**
 * Initialize sidebar state from localStorage
 */
function initializeSidebar() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Initialize dropdown states
    initializeDropdowns();
}

/**
 * Setup sidebar toggle button
 */
function setupSidebarToggle() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Removido o comportamento de hover na sidebar
    // O menu lateral será expandido apenas ao clicar no botão de toggle
}

/**
 * Toggle sidebar expanded/collapsed state
 */
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    
    // Save state to localStorage
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
    
    // Update the toggle button icon
    const toggleIcon = document.querySelector('.sidebar-toggle i');
    
    if (toggleIcon) {
        if (isCollapsed) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
    }
}

/**
 * Setup mobile menu toggle
 */
function setupMobileMenu() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (mobileMenuToggle && sidebar && sidebarOverlay) {
        // Open sidebar on toggle click
        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = ''; // Enable scrolling
        });
    }
}

/**
 * Initialize dropdown menu states from localStorage
 */
function initializeDropdowns() {
    const pathname = (window.location.pathname || '').replace(/\\/g, '/');
    const isFiscalPage = /\/fiscal\/pages\/(nfe|cte|mdfe|eventos)\.php$/i.test(pathname);
    
    // Get saved dropdown states
    let dropdownStates = {};
    
    try {
        const savedStates = localStorage.getItem('sidebarDropdowns');
        
        if (savedStates) {
            dropdownStates = JSON.parse(savedStates);
        }
    } catch (error) {
        console.error('Error parsing saved dropdown states:', error);
    }
    
    // Apply saved states (or force open for current section)
    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
    const dropdowns = document.querySelectorAll('.sidebar-dropdown');
    
    dropdowns.forEach((dropdown, index) => {
        const parentLink = dropdownToggles[index];
        if (!parentLink) return;
        
        const linkText = (parentLink.querySelector('.sidebar-link-text') && parentLink.querySelector('.sidebar-link-text').textContent) || '';
        const isSistemaFiscal = linkText.indexOf('Sistema Fiscal') !== -1;
        const shouldOpen = isFiscalPage && isSistemaFiscal ? true : (dropdownStates[index] === true);
        
        if (shouldOpen) {
            dropdown.style.display = 'block';
            parentLink.classList.add('active');
            const icon = parentLink.querySelector('.dropdown-icon');
            if (icon) icon.classList.add('rotate');
        }
        
        // Marcar link ativo no submenu quando estiver na página fiscal
        if (isFiscalPage && isSistemaFiscal) {
            dropdown.querySelectorAll('a[href]').forEach(function(a) {
                const href = (a.getAttribute('href') || '').replace(/^https?:\/\/[^/]*/, '');
                const hrefPath = href.replace(/^https?:\/\/[^/]*/, '') || href;
                const match = (pathname === hrefPath || pathname === href) || (pathname.endsWith('cte.php') && href.endsWith('cte.php')) || (pathname.endsWith('nfe.php') && href.endsWith('nfe.php')) || (pathname.endsWith('mdfe.php') && href.endsWith('mdfe.php')) || (pathname.endsWith('eventos.php') && href.endsWith('eventos.php'));
                a.classList.toggle('active', !!match);
            });
        }
    });
    
    if (isFiscalPage) {
        saveDropdownStates();
    }
}

/**
 * Setup dropdown toggle functionality
 */
function setupDropdowns() {
    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
    
    if (!dropdownToggles.length) return;
    
    dropdownToggles.forEach((toggle) => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Find corresponding dropdown
            const dropdown = toggle.nextElementSibling;
            
            if (dropdown) {
                // Toggle dropdown visibility
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                    toggle.classList.remove('active');
                    toggle.querySelector('.dropdown-icon').classList.remove('rotate');
                } else {
                    dropdown.style.display = 'block';
                    toggle.classList.add('active');
                    toggle.querySelector('.dropdown-icon').classList.add('rotate');
                }
                
                // Save dropdown states
                saveDropdownStates();
            }
        });
    });
}

/**
 * Save current dropdown states to localStorage
 */
function saveDropdownStates() {
    const dropdowns = document.querySelectorAll('.sidebar-dropdown');
    const states = {};
    
    dropdowns.forEach((dropdown, index) => {
        states[index] = dropdown.style.display === 'block';
    });
    
    localStorage.setItem('sidebarDropdowns', JSON.stringify(states));
}
